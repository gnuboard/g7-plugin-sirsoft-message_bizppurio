<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bizppurio result code → reason (English)
|--------------------------------------------------------------------------
|
| Source: Bizppurio API manual "9. Status_Code" table
| As of: 2026-07-13 (plan appendix C-3, phase 1 scope)
| Scope: common(2000~9070) + SMS(4100~4443) + LMS(6600~6641) + Alimtalk(7000~7523).
|        RCS(8000s)/NaverTalkTalk(part of 5000s) are out of scope (follow-up D15).
|
*/

return [
    // ── Common / send response ───────────────────────────
    '1000' => 'Success',
    '2000' => 'Invalid request parameter',
    '3001' => 'Missing required parameter',
    '3002' => 'Invalid token (expired/revoked)',
    '3004' => 'Request not allowed',
    '3005' => 'Invalid authentication',
    '3006' => 'Account information error',
    '3007' => 'Sender number not registered',
    '3009' => 'Message type error',
    '3011' => 'Temporary processing delay',
    '3013' => 'Temporary system error',
    '5003' => 'Temporary delivery error',
    '5004' => 'Temporary delivery error',
    '5005' => 'Temporary delivery error',
    '9000' => 'Temporary system error',
    '9070' => 'Insufficient balance (SMS)',

    // ── SMS report ───────────────────────────────────────
    '4100' => 'Success',
    '4400' => 'Out of service area',
    '4401' => 'Power off',
    '4402' => 'Storage full',
    '4410' => 'Invalid number',
    '4414' => 'Disconnected/suspended number',
    '4420' => 'Other device error',
    '4430' => 'Spam rejection',
    '4443' => 'Other error',

    // ── LMS report ───────────────────────────────────────
    '6600' => 'Success',
    '6603' => 'Out of service area',
    '6604' => 'Power off',
    '6606' => 'Invalid number',
    '6621' => 'Message length exceeded',
    '6641' => 'Spam blocked',

    // ── Alimtalk report ──────────────────────────────────
    '7000' => 'Success',
    '7106' => 'Deleted sender key',
    '7107' => 'Blocked sender key',
    '7204' => 'Template mismatch',
    '7206' => 'Unapproved template',
    '7308' => 'Phone number error',
    '7320' => 'Receiver blocked',
    '7325' => 'Variable length exceeded',
    '7436' => 'Insufficient wallet balance (Alimtalk)',
    '7523' => 'Other error',
];
