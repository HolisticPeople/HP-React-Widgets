<?php
require_once('wp-load.php');

$keys_to_clean = [
    'group_category_funnel_canonical',
    'group_product_funnel_canonical',
    'group_hp_funnel_seo' // Legacy key that might be lingering
];

foreach ($keys_to_clean as $key) {
    $posts = get_posts([
        'post_type'      => 'acf-field-group',
        'post_status'    => 'any',
        'name'           => $key,
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'DESC', // Keep newest
    ]);

    if (count($posts) > 1) {
        echo "Cleaning up key '$key' (" . count($posts) . " posts found):\n";
        $keep = array_shift($posts);
        echo "- KEEPING: ID {$keep->ID} (Date: {$keep->post_date})\n";
        
        foreach ($posts as $p) {
            echo "- DELETING: ID {$p->ID} (Date: {$p->post_date})\n";
            wp_delete_post($p->ID, true); // Force delete (skip trash)
        }
    } else if (count($posts) === 1) {
        echo "Key '$key' is clean (1 post found: ID {$posts[0]->ID}).\n";
    } else {
        echo "Key '$key' not found in database.\n";
    }
}

// Also check for orphaned field groups with similar titles
$orphans = get_posts([
    'post_type'      => 'acf-field-group',
    'post_status'    => 'any',
    's'              => 'Funnel SEO',
    'posts_per_page' => -1,
]);

echo "\nChecking for other 'Funnel SEO' groups (" . count($orphans) . " found):\n";
foreach ($orphans as $p) {
    $key = $p->post_name;
    if (!in_array($key, $keys_to_clean)) {
        echo "- Found ID {$p->ID} with key '{$key}'. Consider manual cleanup if this is a duplicate.\n";
    }
}
