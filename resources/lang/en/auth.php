<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    // Page Titles
    'login_title' => 'Login - QashierWise',
    'register_title' => 'Register - QashierWise',

    // Tab Switcher
    'login' => 'Login',
    'register' => 'Register',
    'login_tab' => 'Login',
    'register_tab' => 'Register',

    // Login Page
    'welcome_back' => 'Welcome Back',
    'login_subtitle' => 'Sign in to your QashierWise account',
    'email' => 'Email',
    'email_placeholder' => 'name@email.com',
    'password' => 'Password',
    'password_placeholder' => 'Enter your password',
    'sign_in' => 'Sign In',
    'login_as_editor' => 'Login as Editor',
    'remember_me' => 'Remember Me',
    'forgot_password' => 'Forgot Your Password?',
    'login_button' => 'Login',
    'processing' => 'Processing...',
    'back_to_home' => 'Back to home',

    // Admin Login Page
    'admin_login_heading' => 'Sign in to your account',
    'admin_login_email' => 'Email address',
    'admin_login_password' => 'Password',
    'admin_login_remember' => 'Remember me',
    'admin_login_button' => 'Sign in',
    'admin_login_forgot' => 'Forgot password?',
    'admin_login_back_home' => 'Home',

    // Register Page
    'free_trial' => 'Try Free for 14 Days',
    'register_subtitle' => 'Create an account and start using QashierWise now',
    'username' => 'Username',
    'username_placeholder' => 'Enter your username',
    'business_name' => 'Restaurant/Business Name',
    'business_name_placeholder' => 'Example: Nusantara Restaurant',
    'store_name' => 'Restaurant/Business Name',
    'store_name_placeholder' => 'Example: Nusantara Restaurant',
    'store_name_help' => 'This will be the name of your restaurant or business',
    'password_confirmation' => 'Confirm Password',
    'confirm_password' => 'Confirm Password',
    'confirm_password_placeholder' => 'Enter password again',
    'password_confirmation_placeholder' => 'Enter password again',
    'password_min_hint' => 'Minimum 8 characters',
    'register_button' => 'Register Now',
    'register_now' => 'Register Now',
    'already_have_account' => 'Already have an account?',
    'dont_have_account' => "Don't have an account?",

    // Success Messages
    'login_success' => 'Login successful! Redirecting...',
    'register_success' => 'Registration successful! Redirecting...',
    'logout_success' => 'Logged out successfully',
    'email_verified' => 'Email verified successfully',
    'password_reset_sent' => 'Password reset link sent to your email',

    // Error Messages
    'login_failed' => 'Login failed. Please check your credentials.',
    'invalid_credentials' => 'Invalid login credentials',
    'register_failed' => 'Registration failed. Please try again.',
    'validation_error' => 'Please fix the validation errors.',
    'network_error' => 'A network error occurred. Please try again.',
    'user_not_authenticated' => 'User not authenticated',
    'token_expired' => 'Your session has expired. Please login again.',
    'token_invalid' => 'Invalid authentication token.',
    'email_not_registered' => 'The email is not registered in the application',

    // Validation Messages (Auth-specific)
    'name_required' => 'Business name is required',
    'name_max' => 'Business name must not exceed 255 characters',
    'email_required' => 'Email address is required',
    'email_invalid' => 'Please enter a valid email address',
    'email_unique' => 'This email is already registered',
    'password_required' => 'Password is required',
    'password_min' => 'Password must be at least 8 characters',
    'password_confirmed' => 'Password confirmation does not match',

    // Password Reset
    'reset_password' => 'Reset Password',
    'reset_password_subtitle' => 'Enter your email to receive a password reset link',
    'send_reset_link' => 'Send Reset Link',
    'reset_link_sent' => 'Password reset link has been sent to your email',
    'new_password' => 'New Password',
    'confirm_new_password' => 'Confirm New Password',
    'reset_password_button' => 'Reset Password',
    'password_reset_success' => 'Password has been reset successfully',
    'reset_link_expired' => 'This password reset link has expired.',
    'reset_link_expired_description' => 'For security reasons, password reset links are only valid for 60 minutes. Please request a new link to reset your password.',
    'request_new_link' => 'Request New Link',

    // Email Verification
    'verify_email' => 'Verify Email',
    'verify_email_subtitle' => 'We sent a verification code to your email',
    'verify_email_button' => 'Verify Email',
    'enter_verification_code' => 'Enter Verification Code',
    'verification_code_sent' => 'Verification code sent to your email',
    'verification_code_resent' => 'Verification code resent successfully',
    'email_verified_success' => 'Email verified successfully!',
    'verification_failed' => 'Verification failed. Please check your code.',
    'resend_failed' => 'Failed to resend code. Please try again.',
    'didnt_receive_code' => "Didn't receive the code?",
    'resend_code' => 'Resend Code',
    'resend_in' => 'Resend in',
    'sending' => 'Sending...',
    'verifying' => 'Verifying...',
    'back_to_login' => 'Back to login',
    'verification_sent' => 'Verification email has been sent',
    'resend_verification' => 'Resend Verification Email',
    'email_already_verified' => 'Email is already verified',

    // Two-Factor Authentication (if needed in future)
    'two_factor' => 'Two-Factor Authentication',
    'two_factor_code' => 'Authentication Code',
    'two_factor_placeholder' => 'Enter 6-digit code',
    'verify_code' => 'Verify Code',

];
