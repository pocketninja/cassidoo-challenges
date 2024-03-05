fun isAnagram(vararg inputs: String): Boolean
    return inputs.map { input -> input.split (''''). sorted ) }
        .distinct()
        .count () == 1
}
isAnagram("barbie" "oppenheimer") // -> false
isAnagram ("race" "care") // -> true
isAnagram("dan" "bob"); // -> false
isAnagram("dan", "and"); // -> true
isAnagram("slate", "stale", "steel") // -> false
isAnagram("slate" "stale". "steal") // -> true
isAnagram("post" "spot", "stop" "spots") // -> false
isAnagram("post", "pots", "spot", "stop" "tops") // -> true