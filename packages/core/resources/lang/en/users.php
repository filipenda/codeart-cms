<?php

return [
    'title' => 'Users',
    'description' => 'People who can enter the administration.',
    'tab' => ['users' => 'Users', 'invitations' => 'Invitations'],
    'pending_notice' => '{1} :count registration is waiting for approval.|[2,*] :count registrations are waiting for approval.',
    'pending_show' => 'Show',
    'col' => ['user' => 'User', 'role' => 'Role', 'status' => 'Status', 'two_factor' => 'Two-factor', 'last_login' => 'Last sign-in', 'created' => 'Created'],
    'status' => ['active' => 'Active', 'pending' => 'Awaiting approval', 'blocked' => 'Blocked', 'unverified' => 'E-mail unconfirmed', 'deleted' => 'Deleted'],
    'roles' => ['super-admin' => 'Super admin', 'admin' => 'Admin', 'editor' => 'Editor', 'member' => 'Member'],
    'filter' => ['deleted' => 'Deleted', 'only_deleted' => 'Deleted only'],
    'action' => [
        'approve' => 'Approve', 'reject' => 'Reject', 'block' => 'Block', 'unblock' => 'Unblock', 'delete' => 'Delete',
        'restore' => 'Restore', 'force_delete' => 'Delete permanently', 'reset_2fa' => 'Reset two-factor',
    ],
    'confirm' => [
        'approve' => 'Approve this registration? The person gets an e-mail and can sign in.',
        'approve_bulk' => 'Approve the selected registrations?',
        'block' => 'Block this user? They are signed out at once and cannot sign in.',
        'block_bulk' => 'Block the selected users? Rows you may not change are skipped.',
        'unblock' => 'Unblock this user?',
        'delete' => 'Delete this user? They disappear from the list and cannot sign in, but can be restored.',
        'delete_bulk' => 'Delete the selected users? Rows you may not change are skipped.',
        'restore' => 'Restore this user?',
        'force_delete' => 'Delete this user permanently? This cannot be undone. Log entries stay.',
        'reset_2fa' => 'Reset two-factor authentication? The user sets it up again at the next sign-in.',
    ],
    'edit' => [
        'details' => 'Details', 'save' => 'Save', 'role' => 'Role', 'change_role' => 'Change role', 'registration' => 'Registration',
        'reject_reason' => 'Reason for rejecting (optional, goes into the e-mail)', 'danger' => 'Dangerous actions', 'info' => 'Overview',
        'email_hint' => 'Changing the address clears the e-mail confirmation.',
        'danger_hint' => 'These actions ask for a confirmation and your password again.',
    ],
    'msg' => [
        'updated' => 'User saved.', 'nothing_changed' => 'Nothing changed.', 'role_changed' => 'Role changed.',
        'blocked' => 'User blocked.', 'unblocked' => 'User unblocked.', 'deleted' => 'User deleted.',
        'restored' => 'User restored.', 'force_deleted' => 'User permanently deleted.', 'two_factor_reset' => 'Two-factor authentication reset.',
        'approved' => 'Registration approved.', 'already_approved' => 'The user is already approved.', 'rejected' => 'Registration rejected.',
        'not_pending' => 'This registration is no longer waiting for approval.',
    ],
    'widget' => ['pending' => 'Awaiting approval', 'none' => 'No registrations waiting.', 'review' => 'Review'],
    'invite' => [
        'title' => 'Invite a user', 'description' => 'The person gets an e-mail with a link to set a password. This is the only way to add a user.',
        'message' => 'Short message (optional)', 'message_hint' => 'Shown in the invitation e-mail.', 'send' => 'Send invitation',
    ],
    'invitation' => [
        'description' => 'Invitations that have not been accepted yet.', 'valid' => 'Valid', 'expired' => 'Expired', 'expires' => 'Expires',
        'resend' => 'Resend', 'resend_confirm' => 'Send the invitation again? The old link stops working.',
        'revoke' => 'Revoke', 'revoke_confirm' => 'Revoke the invitation? The link stops working.',
        'sent' => 'Invitation sent.', 'resent' => 'Invitation sent again.', 'revoked' => 'Invitation revoked.',
    ],
];
