#!/bin/bash

cd ..
php artisan migrate:fresh
php artisan db:seed
php artisan db:seed --class=AdminDemoDataSeeder
php artisan db:seed --class=AdminAiAgentSeeder
cd command
# ./wa-setup.sh