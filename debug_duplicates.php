<?php
require_once('wp-load.php');

$key = 'group_category_funnel_canonical';
$posts = get_posts([
    'post_type'      => 'acf-field-group',
    'post_status'    => 'any',
    'name'           => $key, // ACF uses the key as post_name
    'posts_per_page' => -1,
]);

echo "Found " . count($posts) . " posts for key '$key':\n";
foreach ($posts as $p) {
    echo "- ID: {$p->ID}, Title: {$p->post_title}, Status: {$p->post_status}, Date: {$p->post_date}\n";
}

// Check other canonical group
$key2 = 'group_product_funnel_canonical';
$posts2 = get_posts([
    'post_type'      => 'acf-field-group',
    'post_status'    => 'any',
    'name'           => $key2,
    'posts_per_page' => -1,
]);

echo "\nFound " . count($posts2) . " posts for key '$key2':\n";
foreach ($posts2 as $p) {
    echo "- ID: {$p->ID}, Title: {$p->post_title}, Status: {$p->post_status}, Date: {$p->post_date}\n";
}
