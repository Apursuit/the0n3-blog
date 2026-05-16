<?php

return [
    'title' => 'the0n3',
    'author' => 'the0n3',
    # Sitemap 使用此域名作为站点根地址
    'url' => 'https://blog.the0n3.top',
    'description' => 'the0n3',
    'canonical' => 'https://blog.the0n3.top',
    'og_image' => 'https://blog.the0n3.top/images/og-default.png',
    'og_locale' => 'zh_CN',
    // Giscus 评论系统（默认关闭，需填写自己的配置）
    'giscus' => [
        'enabled' => true,
        'repo' => 'Apursuit/the0n3-blog',
        'repo_id' => 'R_kgDORvPPbA',
        'category' => 'Announcements',
        'category_id' => 'DIC_kwDORvPPbM4C5KAt',
        'mapping' => 'pathname',
        'strict' => '0',
        'reactions_enabled' => '1',
        'emit_metadata' => '0',
        'input_position' => 'bottom',
        'theme' => 'light',
        'lang' => 'zh-CN',
    ],
];
