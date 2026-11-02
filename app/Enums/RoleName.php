<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Premium = 'premium';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Administrator'),
            self::Editor => __('Editor'),
            self::Premium => __('Premium student'),
            self::Student => __('Student'),
        };
    }

    /**
     * Permissions granted to the role. Admins bypass checks through Gate::before.
     *
     * @return list<PermissionName>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => PermissionName::cases(),
            self::Editor => [
                PermissionName::AccessAdmin,
                PermissionName::ManageCategories,
                PermissionName::ManageLessons,
                PermissionName::PublishLessons,
                PermissionName::ViewPremiumLessons,
            ],
            self::Premium => [PermissionName::ViewPremiumLessons],
            self::Student => [],
        };
    }
}
