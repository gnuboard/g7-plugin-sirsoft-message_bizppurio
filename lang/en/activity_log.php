<?php

return [
    // Action labels — unique actions are defined with full dotted keys (plan §3).
    // ActivityLog::getActionLabelAttribute resolves this array first (plugin lang full action key).
    'action' => [
        'sirsoft-message_bizppurio' => [
            'alimtalk_template' => [
                'create' => 'Register alimtalk template',
                'update' => 'Update alimtalk template',
                'delete' => 'Delete alimtalk template',
                'request' => 'Request alimtalk template inspection',
                'cancel_request' => 'Cancel alimtalk template inspection request',
                'stop' => 'Stop alimtalk template',
                'reuse' => 'Resume alimtalk template',
                'cancel_approval' => 'Cancel alimtalk template approval',
                'release' => 'Release alimtalk template from dormancy',
            ],
        ],
    ],

    // Description bodies — standard pattern activity_log.description.X (resolved by DescriptionResolver).
    'description' => [
        'alimtalk_template_create' => 'Registered alimtalk template (:template_name)',
        'alimtalk_template_update' => 'Updated alimtalk template (:template_code)',
        'alimtalk_template_delete' => 'Deleted alimtalk template (:template_code)',
        'alimtalk_template_request' => 'Requested alimtalk template inspection (:template_code)',
        'alimtalk_template_cancel_request' => 'Canceled alimtalk template inspection request (:template_code)',
        'alimtalk_template_stop' => 'Stopped alimtalk template (:template_code)',
        'alimtalk_template_reuse' => 'Resumed alimtalk template (:template_code)',
        'alimtalk_template_cancel_approval' => 'Canceled alimtalk template approval (:template_code)',
        'alimtalk_template_release' => 'Released alimtalk template from dormancy (:template_code)',
    ],
];
