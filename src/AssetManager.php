<?php

namespace App;

class AssetManager
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function cleanup(): void
    {
        if (is_dir($this->config['dist_path'])) {
            Utils::deleteDirectory($this->config['dist_path']);
        }
        mkdir($this->config['dist_path'], 0755, true);
    }

    public function copy(): void
    {
        if (is_dir($this->config['assets_path'])) {
            Utils::copyDirectory($this->config['assets_path'], $this->config['dist_path'] . '/assets');
        }
        if (is_dir($this->config['public_path'])) {
            Utils::copyDirectory($this->config['public_path'], $this->config['dist_path']);
        }
        if (is_dir($this->config['images_path'])) {
            Utils::copyDirectory($this->config['images_path'], $this->config['dist_path'] . '/images');
        }
    }
}
