import json
import math
from collections import defaultdict
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
TRAINING_DATA_PATH = ROOT / "library" / "data" / "recommendation_training.json"
MODEL_OUTPUT_PATH = ROOT / "library" / "data" / "ml_recommendations.json"
MAX_RECOMMENDATIONS_PER_USER = 12
MAX_NEIGHBORS_PER_BOOK = 8
MIN_CO_RATERS = 1


def load_training_data():
    if not TRAINING_DATA_PATH.exists():
        raise FileNotFoundError(
            f"Training data file not found: {TRAINING_DATA_PATH}. "
            f"Run `php library/export_recommendation_data.php` first."
        )
    with TRAINING_DATA_PATH.open("r", encoding="utf-8") as handle:
        return json.load(handle)


def build_rating_maps(reviews):
    user_ratings = defaultdict(dict)
    book_ratings = defaultdict(dict)
    for review in reviews:
        student_id = str(review.get("StudentId", "")).strip()
        book_id = int(review.get("BookId", 0))
        rating = float(review.get("Rating", 0))
        if not student_id or book_id <= 0:
            continue
        user_ratings[student_id][book_id] = rating
        book_ratings[book_id][student_id] = rating
    return user_ratings, book_ratings


def compute_user_means(user_ratings):
    means = {}
    for student_id, ratings in user_ratings.items():
        if ratings:
            means[student_id] = sum(ratings.values()) / float(len(ratings))
        else:
            means[student_id] = 0.0
    return means


def compute_item_similarity(user_ratings, user_means):
    dot_products = defaultdict(lambda: defaultdict(float))
    norms = defaultdict(float)
    overlap = defaultdict(lambda: defaultdict(int))

    for student_id, ratings in user_ratings.items():
        if len(ratings) < 2:
            continue
        mean_rating = user_means.get(student_id, 0.0)
        centered = {book_id: (rating - mean_rating) for book_id, rating in ratings.items()}
        book_ids = sorted(centered.keys())
        for book_id in book_ids:
            norms[book_id] += centered[book_id] * centered[book_id]
        for index, left_book_id in enumerate(book_ids):
            for right_book_id in book_ids[index + 1 :]:
                left_value = centered[left_book_id]
                right_value = centered[right_book_id]
                dot_products[left_book_id][right_book_id] += left_value * right_value
                dot_products[right_book_id][left_book_id] += left_value * right_value
                overlap[left_book_id][right_book_id] += 1
                overlap[right_book_id][left_book_id] += 1

    similarity = defaultdict(dict)
    for left_book_id, neighbors in dot_products.items():
        for right_book_id, dot_value in neighbors.items():
            if overlap[left_book_id][right_book_id] < MIN_CO_RATERS:
                continue
            left_norm = math.sqrt(norms[left_book_id])
            right_norm = math.sqrt(norms[right_book_id])
            if left_norm <= 0 or right_norm <= 0:
                continue
            score = dot_value / (left_norm * right_norm)
            if score > 0:
                similarity[left_book_id][right_book_id] = score
    return similarity, overlap


def build_user_recommendations(user_ratings, user_means, similarity, books):
    all_book_ids = sorted(books.keys())
    user_recommendations = {}
    for student_id, ratings in user_ratings.items():
        rated_books = set(ratings.keys())
        if not rated_books:
            continue
        mean_rating = user_means.get(student_id, 0.0)
        candidate_scores = defaultdict(float)
        candidate_weights = defaultdict(float)

        for rated_book_id, raw_rating in ratings.items():
            centered_rating = raw_rating - mean_rating
            for neighbor_book_id, sim_score in similarity.get(rated_book_id, {}).items():
                if neighbor_book_id in rated_books:
                    continue
                candidate_scores[neighbor_book_id] += sim_score * centered_rating
                candidate_weights[neighbor_book_id] += abs(sim_score)

        ranked = []
        for candidate_book_id in all_book_ids:
            if candidate_book_id in rated_books:
                continue
            weight = candidate_weights.get(candidate_book_id, 0.0)
            if weight <= 0:
                continue
            predicted_rating = mean_rating + (candidate_scores[candidate_book_id] / weight)
            ranked.append(
                {
                    "book_id": candidate_book_id,
                    "score": round(predicted_rating, 4),
                }
            )

        ranked.sort(key=lambda item: (-item["score"], books[item["book_id"]]["BookName"].lower()))
        if ranked:
            user_recommendations[student_id] = ranked[:MAX_RECOMMENDATIONS_PER_USER]
    return user_recommendations


def build_book_neighbors(similarity, books):
    neighbors = {}
    for book_id, neighbor_map in similarity.items():
        ranked = [
            {"book_id": neighbor_book_id, "score": round(score, 4)}
            for neighbor_book_id, score in neighbor_map.items()
            if neighbor_book_id in books
        ]
        ranked.sort(key=lambda item: (-item["score"], books[item["book_id"]]["BookName"].lower()))
        if ranked:
            neighbors[str(book_id)] = ranked[:MAX_NEIGHBORS_PER_BOOK]
    return neighbors


def main():
    payload = load_training_data()
    books = {
        int(book["id"]): book
        for book in payload.get("books", [])
        if int(book.get("id", 0)) > 0
    }
    reviews = payload.get("reviews", [])

    user_ratings, _book_ratings = build_rating_maps(reviews)
    user_means = compute_user_means(user_ratings)
    similarity, overlap = compute_item_similarity(user_ratings, user_means)
    user_recommendations = build_user_recommendations(user_ratings, user_means, similarity, books)
    book_neighbors = build_book_neighbors(similarity, books)

    model = {
        "generated_at": payload.get("generated_at"),
        "model_type": "item_based_collaborative_filtering",
        "training_summary": {
            "books": len(books),
            "reviews": len(reviews),
            "users_with_ratings": len(user_ratings),
        },
        "user_recommendations": user_recommendations,
        "book_neighbors": book_neighbors,
        "overlap_counts": {
            str(book_id): {str(neighbor_id): count for neighbor_id, count in neighbors.items()}
            for book_id, neighbors in overlap.items()
        },
    }

    MODEL_OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    with MODEL_OUTPUT_PATH.open("w", encoding="utf-8") as handle:
        json.dump(model, handle, indent=2)

    print(f"Saved ML recommendation model to: {MODEL_OUTPUT_PATH}")
    print(
        "Training summary:",
        json.dumps(model["training_summary"], indent=2),
    )


if __name__ == "__main__":
    main()
