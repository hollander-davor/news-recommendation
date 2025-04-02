import json #hanldes JSON data
import redis #interacts with Redis
import pymongo # interacts with mongo
import threading
import os #used to get env and file paths
from concurrent.futures import ThreadPoolExecutor #manages multi-threaded execution
from datetime import datetime, timedelta #used to work with timestamps and dates
from collections import Counter, defaultdict #helps with counting occurences of elements

# Get the current script directory.
script_dir = os.path.dirname(os.path.abspath(__file__))
# Navigate from vendor/davor/news-recommendation/src/Commands to the Laravel base directory.
# Assuming your project's base is 4 levels up:
base_path = os.path.abspath(os.path.join(script_dir,"..", "..", "..", "..", ".."))
# Construct the path to python_recommendations_config.json in the Laravel config directory.
config_path = os.path.join(base_path, "config", "python_recommendations_config.json")


# Load configuration into config variable
with open(config_path, "r") as config_file:
    config = json.load(config_file)  # Ensure this loads a dictionary


# Redis configuration from .env
redis_host = os.getenv('REDIS_HOST', '127.0.0.1')  # Default to localhost if not set
redis_port = int(os.getenv('REDIS_PORT', 6379))    # Default to 6379 if not set
redis_password = os.getenv('REDIS_PASSWORD', None)  # Default to no password if not set
redis_db = int(os.getenv('REDIS_DB', 0))           # Default to DB 0 if not set
redis_prefix = os.getenv('REDIS_PREFIX', '')      # Default to DB 0 if not set


# MongoDB configuration from .env
mongo_host = os.getenv('DB_HOST_MONGO', 'mongo')  # Mongo host as per your env file
mongo_port = int(os.getenv('DB_PORT_MONGO', 27017))  # Mongo port from your .env file
mongo_db_name = os.getenv('DB_DATABASE_MONGO', 'mongo_test')  # Mongo database name
mongo_user = os.getenv('DB_USERNAME_MONGO', 'root')  # Mongo username
mongo_password = os.getenv('DB_PASSWORD_MONGO', 'cubes')  # Mongo password

# Redis connection
redis_client = redis.Redis(
   redis_host,
    redis_port,
    decode_responses=True
)
# MongoDB connection
mongo_client = pymongo.MongoClient(f"mongodb://{mongo_user}:{mongo_password}@" + mongo_host)
db = mongo_client[mongo_db_name]
user_collection = db[config["users_collection"]]
articles_collection = db[config["articles_collection"]]

# Get today's date
todays_date = datetime.now().strftime("%d-%m-%Y")


def process_reader(redis_key):
    # get value bahind redis key
    articles_string = redis_client.get(redis_key)
    # get article ids
    articles_ids_array = list(set(articles_string.split('|'))) if articles_string else [] #split explodes string, set removes duplicates, list converts it to list
    # Dictionary to store tags count per site
    article_tags_summed_dict = defaultdict(list)
    # Fetch article tags from MongoDB and organize by site
    for article_id in articles_ids_array:
        article_mongo = articles_collection.find_one({"article_id": int(article_id)})
        if article_mongo and 'tags' in article_mongo:
            site_key = f"site_{article_mongo['site_id']}"
            article_tags_summed_dict[site_key].extend(article_mongo['tags'])
            
    #get user and firebase uid
    user_id, firebase_uid = extract_user_id(redis_key)
    # check if we have user with those ids
    existing_user = find_user(user_id, firebase_uid)
    if existing_user:
        update_existing_user(existing_user, articles_ids_array)
    else:
        create_new_user(user_id, firebase_uid, articles_ids_array)

    cleanup_old_data(user_id)
    redis_client.delete(redis_key)


def extract_user_id(redis_key):
    # Extract user id and firebase uid if exists    
    # Check if the Redis key contains '###' it means we have firebase uid
    if "###" in redis_key:
        parts = redis_key.split("###")
        user_id = parts[0].replace(config["redis_reader_prefix"] + "reader-", "")
        firebase_uid = parts[1]  # Firebase UID exists
    else:
        user_id = redis_key.replace(config["redis_reader_prefix"] + "reader-", "")
        firebase_uid = None  # No Firebase UID in this case

    return user_id, firebase_uid



def find_user(user_id, firebase_uid):
    # find if we have user with any of the credentials
    query = {"firebase_uid": firebase_uid} if firebase_uid else {"user_id": user_id}
    return user_collection.find_one(query)

def update_existing_user(user, articles_ids):
    # Update tags occurrences
    tags_occurrences = count_tag_occurences(articles_ids)

    # Load existing user tags
    user_tags = json.loads(user.get("tags", "{}"))

    # Merge new tags into the existing structure
    if todays_date in user_tags:
        existing_tags = json.loads(user_tags[todays_date])
        for site, new_tags in tags_occurrences.items():
            existing_tags[site] = {**existing_tags.get(site, {}), **new_tags}
        user_tags[todays_date] = json.dumps(existing_tags, ensure_ascii=False)
    else:
        user_tags[todays_date] = json.dumps(tags_occurrences, ensure_ascii=False)

    # Serialize user tags properly
    user["tags"] = json.dumps(user_tags, ensure_ascii=False)

    # Update read news
    existing_read_news = set(map(int, json.loads(user.get("read_news", "[]"))))
    all_read_news = existing_read_news | set(map(int, articles_ids))
    user["read_news"] = json.dumps(list(all_read_news))  # Ensure JSON string format

    # Generate recommendations
    recommendations = recommended_articles_weighted(user_tags, list(all_read_news))
    user["news_recommendation"] = json.dumps(recommendations, ensure_ascii=False)

    # Update timestamps
    user["latest_update"] = todays_date
    user["updated_at"] = datetime.utcnow()

    # Save to MongoDB
    user_collection.update_one({"_id": user["_id"]}, {"$set": user})

def create_new_user(user_id, firebase_uid, articles_ids):
    # Count tag occurrences
    tags_occurrences = count_tag_occurences(articles_ids)

    if not tags_occurrences:
        return

    # Format tags correctly
    formatted_tags = json.dumps({todays_date: json.dumps(tags_occurrences, ensure_ascii=False)}, ensure_ascii=False)

    new_user = {
        "user_id": user_id,
        "firebase_uid": firebase_uid,
        "read_news": json.dumps(articles_ids),
        "tags": formatted_tags,
        "news_recommendation": json.dumps(recommended_articles_weighted({todays_date: tags_occurrences}, articles_ids), ensure_ascii=False),
        "latest_update": todays_date,
        "created_at": datetime.utcnow(),
        "updated_at": datetime.utcnow(),
    }
    
    user_collection.insert_one(new_user)



def count_tag_occurences(articles_ids):
    articles_ids = [int(id) for id in articles_ids]
    tags_count = {}

    # Fetch articles from MongoDB
    articles = articles_collection.find({"article_id": {"$in": articles_ids}}, {"tags": 1, "site_id": 1})

    for article in articles:
        site_key = f"site_{article['site_id']}"
        tags_count.setdefault(site_key, {})

        for tag in article.get("tags", []):
            tags_count[site_key][tag] = tags_count[site_key].get(tag, 0) + 1

    # Sort tags and return properly formatted dictionary
    sorted_tags_count = {
        site_key: dict(sorted(tags.items(), key=lambda x: x[1], reverse=True))
        for site_key, tags in tags_count.items()
    }

    return sorted_tags_count



def recommended_articles_weighted(user_tags_array, read_news=None):
    read_news = set(map(int, read_news)) if isinstance(read_news, list) else set(map(int, json.loads(read_news)))
    all_tags_dict = defaultdict(Counter)

    for one_day_tags in user_tags_array.values():
        if isinstance(one_day_tags, str):
            one_day_tags = json.loads(one_day_tags)
        for site_key, site_tags in one_day_tags.items():
            all_tags_dict[site_key].update(site_tags)

    array_length = config["tags_array_length"]
    recommended_articles_count = config["recommended_articles_count"]
    excluded_categories = config["exclude_categories"]
    excluded_subcategories = config["exclude_subcategories"]
    days_limit = config["weighted_algorithm_days"]
    date_treshold = datetime.utcnow() - timedelta(days=days_limit)
    recommended_articles_final = {}

    for site_key, tags in all_tags_dict.items():
        sorted_tags = dict(tags.most_common(array_length))

        query = {
            "published": 1,
            "article_id": {"$nin": list(read_news)},
            "publish_at": {"$gte": date_treshold}
        }

        if site_key in excluded_categories:
            query["category"] = {"$nin": excluded_categories[site_key]}
        if site_key in excluded_subcategories:
            query["subcategory"] = {"$nin": excluded_subcategories[site_key]}

        articles = articles_collection.find(query, {"article_id": 1, "tags": 1})
        weighted_articles = defaultdict(int)

        for article in articles:
            article_tags = article.get("tags", [])
            for tag in sorted_tags:
                if tag in article_tags:
                    weighted_articles[article["article_id"]] += sorted_tags[tag]

        recommended_articles = [
            article_id for article_id, _ in sorted(weighted_articles.items(), key=lambda item: item[1], reverse=True)[:recommended_articles_count]
        ]
        
        recommended_articles_final[site_key] = recommended_articles

    return recommended_articles_final


def cleanup_old_data(user_id):
    #delete old tags for user
    retention_days = config["days_keep_data"]
    old_date = (datetime.now() - timedelta(days=retention_days)).strftime("%d-%m-%Y")

    user = user_collection.find_one({"user_id": user_id})
    if user:
        user_tags = user.get("tags", {})
        if old_date in user_tags:
            del user_tags[old_date]
            user_collection.update_one({"_id": user["_id"]}, {"$set": {"tags": user_tags}})


def main():
    """Main function to fetch and process readers from Redis using multithreading."""
    redis_keys = redis_client.keys(config["redis_reader_prefix"] + "reader-*")
    max_users = config.get("limit_users", len(redis_keys))
    with ThreadPoolExecutor(max_workers=config["max_workers"]) as executor:
        executor.map(process_reader, redis_keys[:max_users])


if __name__ == "__main__":
    main()
