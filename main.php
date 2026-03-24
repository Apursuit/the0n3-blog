<?php

require __DIR__ . '/vendor/autoload.php';

use App\Generator;
use App\Utils;

date_default_timezone_set('Asia/Shanghai');

if ($argc < 2) {
    echo "Usage: php main.php <command> [options]\n";
    echo "Commands:\n";
    echo "  new <title>   Create a new post.\n";
    echo "  build         Build the static site.\n";
    exit(1);
}

$command = $argv[1];

switch ($command) {
    case 'new':
        if ($argc < 3) {
            echo "Usage: php main.php new <title>\n";
            exit(1);
        }
        $title = $argv[2];
        $date = date('Y-m-d H:i:s');
        $permalink = '/posts/' . Utils::generateShortId() . '/';

        $content = <<<EOT
---
title: {$title}
date: {$date}
permalink: {$permalink}
tags: []
categories: []
draft: false
sidebar: true
---

在这里开始写正文。
EOT;
        $filename = 'posts/' . strtolower(str_replace(' ', '-', $title)) . '.md';
        if (file_exists($filename)) {
            $filename = 'posts/' . strtolower(str_replace(' ', '-', $title)) . '-' . time() . '.md';
        }

        // To simplify, we just print it. The user can redirect it to a file.
        echo "New post template created. Copy the following content to a new .md file in the 'posts' directory:\n\n";
        echo $content;
        break;

    case 'build':
        $config = [
            'posts_path' => __DIR__ . '/posts',
            'assets_path' => __DIR__ . '/assets',
            'public_path' => __DIR__ . '/public',
            'images_path' => __DIR__ . '/images',
            'templates_path' => __DIR__ . '/templates',
            'dist_path' => __DIR__ . '/dist',
            'logs_path' => __DIR__ . '/logs',
            'site' => require __DIR__ . '/config/site.php',
        ];

        Utils::initLogging($config['logs_path']);
        $generator = new Generator($config);
        $generator->run();
        break;

    default:
        echo "Unknown command: {$command}\n";
        exit(1);
}
