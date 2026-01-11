<?php
require_once('wp-load.php');

$posts = get_posts([
    'post_type'      => 'acf-field-group',
    'post_status'    => 'any',
    'posts_per_page' => -1,
]);

echo "Total field groups found: " . count($posts) . "\n\n";

$groups_by_key = [];

foreach ($posts as $p) {
    // ACF usually stores the key in the post_name
    // But let's check the content too just in case
    $data = unserialize($p->post_content);
    $key = $data['key'] ?? $p->post_name;
    
    if (!isset($groups_by_key[$key])) {
        $groups_by_key[$key] = [];
    }
    
    $groups_by_key[$key][] = [
        'ID' => $p->ID,
        'slug' => $p->post_name,
        'title' => $p->post_title,
        'status' => $p->post_status,
        'date' => $p->post_date
    ];
}

foreach ($groups_by_key as $key => $instances) {
    if (count($instances) > 1) {
        echo "DUPLICATE KEY: $key (" . count($instances) . " instances)\n";
        foreach ($instances as $inst) {
            echo "  - ID: {$inst['ID']}, Slug: {$inst['slug']}, Title: {$inst['title']}, Status: {$inst['status']}, Date: {$inst['date']}\n";
        }
        echo "\n";
    }
}
