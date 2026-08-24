<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attachments.view');
    }

    public function view(User $user, Attachment $attachment): bool
    {
        return $user->can('attachments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('attachments.create');
    }

    public function update(User $user, Attachment $attachment): bool
    {
        return $user->can('attachments.update');
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $user->can('attachments.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('attachments.delete');
    }
}
