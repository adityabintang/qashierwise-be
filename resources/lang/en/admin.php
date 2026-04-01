<?php

return [
    'resources' => [
        'blog_post' => [
            'label' => 'Blog Post',
            'plural' => 'Blog Posts',
            'notifications' => [
                'created_title' => 'Blog Post Saved Successfully',
                'created_body' => 'Article ":title" has been created.',
                'updated_title' => 'Blog Post Saved Successfully',
                'updated_body' => 'Article ":title" has been updated.',
                'failed_title' => 'Failed to Save Blog Post',
                'failed_body' => 'Please review the invalid form input.',
                'invalid_publish_date_body' => 'Publish date cannot be greater than the current time for Published status.',
                'invalid_schedule_date_body' => 'Publish date must be greater than the current time for Scheduled status.',
            ],
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
                'publish_date_helper' => 'Input follows the viewer local timezone (WIB/WITA/WIT/UTC). The system stores and compares time in UTC.',
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
