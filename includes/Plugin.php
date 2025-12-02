<?php
namespace HP_RW;

class Plugin
{
    public static function init()
    {
        $assetLoader = new AssetLoader();
        $assetLoader->register();

        $shortcodeRegistry = new ShortcodeRegistry($assetLoader);
        $shortcodeRegistry->register();
    }
}
