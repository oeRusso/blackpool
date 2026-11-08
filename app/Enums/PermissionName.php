<?php

namespace App\Enums;

enum PermissionName: string
{
    case AccessAdmin = 'access admin';
    case ManageCategories = 'manage categories';
    case ManageLessons = 'manage lessons';
    case PublishLessons = 'publish lessons';
    case ManageUsers = 'manage users';
    case ManageRoles = 'manage roles';
    case ViewPremiumLessons = 'view premium lessons';

    public function label(): string
    {
        return match ($this) {
            self::AccessAdmin => __('Access the admin panel'),
            self::ManageCategories => __('Create, edit and delete categories'),
            self::ManageLessons => __('Create, edit and delete lessons'),
            self::PublishLessons => __('Publish and unpublish lessons'),
            self::ManageUsers => __('Manage users and their roles'),
            self::ManageRoles => __('Edit role permissions'),
            self::ViewPremiumLessons => __('Watch premium lessons'),
        };
    }
}
