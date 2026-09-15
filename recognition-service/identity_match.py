"""
Identity decision for numerical face comparison.

The matcher does not "recognize a person by looking." It compares numbers:
  * ArcFace — cosine similarity of 128-D descriptors (higher is closer)
  * LBPH    — histogram distance (lower is closer)

A unique identity is accepted only when:
  1. the best score/distance passes the threshold, and
  2. the runner-up is not close enough to be a lookalike.
Otherwise attendance is not recorded.
"""


def is_lookalike_similarity(best_id, best_score, second_id, second_score, threshold, min_margin):
    """True when two enrolled identities are too close (higher score = better)."""
    if best_id is None or second_id is None or int(second_id) == int(best_id):
        return False
    if second_score >= threshold:
        return True
    return (best_score - second_score) < min_margin


def is_lookalike_distance(best_id, best_dist, second_id, second_dist, threshold, min_margin):
    """True when two enrolled identities are too close (lower distance = better)."""
    if best_id is None or second_id is None or int(second_id) == int(best_id):
        return False
    if second_dist <= threshold:
        return True
    return (second_dist - best_dist) < min_margin


def decide_similarity(best_id, best_score, second_id, second_score, threshold, min_margin):
    """ArcFace-style: return (matched, reason)."""
    if best_id is None or best_score < threshold:
        return False, "BELOW_THRESHOLD"
    if is_lookalike_similarity(best_id, best_score, second_id, second_score, threshold, min_margin):
        return False, "LOOKALIKE"
    return True, "OK"


def decide_distance(best_id, best_dist, second_id, second_dist, threshold, min_margin):
    """LBPH-style: return (matched, reason)."""
    if best_id is None or best_dist > threshold:
        return False, "BELOW_THRESHOLD"
    if is_lookalike_distance(best_id, best_dist, second_id, second_dist, threshold, min_margin):
        return False, "LOOKALIKE"
    return True, "OK"
