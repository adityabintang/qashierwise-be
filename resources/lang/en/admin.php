<?php

return [
    'resources' => [
        'blog_post' => [
            'label' => 'Blog Post',
            'plural' => 'Blog Posts',
            'sections' => [
                'content' => 'Content',
                'media' => 'Media',
                'seo' => 'SEO',
                'publishing' => 'Publishing',
            ],
            'fields' => [
                'title' => 'Title',
                'slug' => 'Slug',
                'category' => 'Category',
                'excerpt' => 'Excerpt',
                'content' => 'Content',
                'featured_image' => 'Featured Image',
                'featured_image_helper' => 'Image will be automatically converted to WebP and resized to max 1920px width.',
                'seo_title' => 'SEO Title',
                'seo_description' => 'SEO Description',
                'og_image' => 'OG Image',
                'og_image_helper' => 'Image for social media preview (1200x630px). If not uploaded, the Media image will be used. Image will be automatically converted to WebP and resized to max 1200px width.',
                'status' => 'Status',
                'publish_date' => 'Publish Date',
                'publish_date_helper' => 'Input uses WIB timezone. System stores time in UTC.',
                'tags' => 'Tags',
                'author' => 'Author',
                'published_at' => 'Published At',
                'created_at' => 'Created At',
                'updated_at' => 'Updated At',
            ],
            'filters' => [
                'status' => 'Status',
                'category' => 'Category',
            ],
        ],
        'blog_category' => [
            'label' => 'Blog Category',
            'plural' => 'Blog Categories',
            'sections' => [
                'details' => 'Category Details',
                'statistics' => 'Statistics',
            ],
            'fields' => [
                'name' => 'Name',
                'slug' => 'Slug',
                'description' => 'Description',
                'is_active' => 'Active',
                'posts_count' => 'Total Posts',
            ],
        ],
        'blog_tag' => [
            'label' => 'Blog Tag',
            'plural' => 'Blog Tags',
            'sections' => [
                'details' => 'Tag Details',
                'statistics' => 'Statistics',
            ],
            'fields' => [
                'name' => 'Name',
                'slug' => 'Slug',
                'posts_count' => 'Total Posts',
            ],
        ],
    ],
];
