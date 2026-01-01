<?php
$bio = '<p>Dr. Gabriel Cousens is a world-renowned holistic physician and the founder of the <a href="https://treeoflifecenterus.com/" target="_blank" rel="noopener noreferrer">Tree of Life Rejuvenation Center</a>. With over 40 years of experience, he has supervised thousands through detoxification protocols, claiming the liver/gallbladder flush is one of the best practices for improving health. Learn more about our <a href="/shop/">wellness products</a>.</p>';
update_post_meta(128781, 'authority_bio', $bio);
\HP_RW\Services\FunnelConfigLoader::clearCache(128781);
echo "Bio updated and cache cleared.\n";


