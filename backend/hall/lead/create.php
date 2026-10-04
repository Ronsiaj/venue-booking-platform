<?php

require_once __DIR__ . '/../../config/database.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/*
|--------------------------------------------------------------------------
| METHOD VALIDATION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Method not allowed. Use POST."
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | DATABASE CONNECTION
    |--------------------------------------------------------------------------
    */

    global $conn;

    if (
        !isset($conn) ||
        !($conn instanceof mysqli)
    ) {
        throw new Exception(
            "Database connection is not available."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | READ JSON BODY
    |--------------------------------------------------------------------------
    */

    $rawInput = file_get_contents("php://input");

    if (
        $rawInput === false ||
        trim($rawInput) === ''
    ) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Request body is required."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DECODE JSON
    |--------------------------------------------------------------------------
    */

    $data = json_decode(
        $rawInput,
        true
    );

    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($data)
    ) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Invalid JSON request body."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | ALLOWED FIELDS
    |--------------------------------------------------------------------------
    */

    $allowedFields = [
        'customer_name',
        'customer_phone',
        'customer_email',
        'hall_id',
        'event_type',
        'event_date',
        'event_time',
        'guest_count',
        'food_required',
        'decoration_required',
        'other_requirements'
    ];

    foreach ($data as $key => $value) {

        if (!in_array($key, $allowedFields, true)) {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" => "Unknown field: {$key}"
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REQUIRED FIELDS
    |--------------------------------------------------------------------------
    */

    $requiredFields = [
        'customer_name',
        'customer_phone'
    ];

    foreach ($requiredFields as $field) {

        if (!array_key_exists($field, $data)) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "{$field} is required."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER NAME
    |--------------------------------------------------------------------------
    */

    $customerName = trim(
        (string)$data['customer_name']
    );

    if ($customerName === '') {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "Customer name cannot be empty."
        ]);

        exit;
    }

    if (
        mb_strlen($customerName) < 2 ||
        mb_strlen($customerName) > 150
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "Customer name must be between 2 and 150 characters."
        ]);

        exit;
    }

    if (
        !preg_match(
            "/^[\p{L}][\p{L}\s.'-]*$/u",
            $customerName
        )
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "Customer name contains invalid characters."
        ]);

        exit;
    }

    if (
        preg_match(
            '/\s{2,}/',
            $customerName
        )
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "Customer name cannot contain multiple consecutive spaces."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER PHONE
    |--------------------------------------------------------------------------
    */

    $customerPhone = trim(
        (string)$data['customer_phone']
    );

    $customerPhone = preg_replace(
        '/[\s\-\(\)]/',
        '',
        $customerPhone
    );

    if (
        !preg_match(
            '/^\d{10,15}$/',
            $customerPhone
        )
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "Customer phone must contain 10 to 15 digits."
        ]);

        exit;
    }

    if (
        preg_match(
            '/^(\d)\1+$/',
            $customerPhone
        )
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "Invalid customer phone number."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER EMAIL
    |--------------------------------------------------------------------------
    */

    $customerEmail = null;

    if (
        array_key_exists('customer_email', $data) &&
        $data['customer_email'] !== null &&
        trim((string)$data['customer_email']) !== ''
    ) {

        $customerEmail = strtolower(
            trim(
                (string)$data['customer_email']
            )
        );

        if (strlen($customerEmail) > 150) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Customer email cannot exceed 150 characters."
            ]);

            exit;
        }

        if (
            !filter_var(
                $customerEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Invalid customer email address."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HALL ID
    |--------------------------------------------------------------------------
    */

    $hallId = null;

    if (
        array_key_exists('hall_id', $data) &&
        $data['hall_id'] !== null &&
        $data['hall_id'] !== ''
    ) {

        if (
            filter_var(
                $data['hall_id'],
                FILTER_VALIDATE_INT
            ) === false ||
            (int)$data['hall_id'] <= 0
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "hall_id must be a valid positive integer."
            ]);

            exit;
        }

        $hallId = (int)$data['hall_id'];
    }

    /*
    |--------------------------------------------------------------------------
    | EVENT TYPE
    |--------------------------------------------------------------------------
    */

    $eventType = null;

    if (
        array_key_exists('event_type', $data) &&
        $data['event_type'] !== null &&
        trim((string)$data['event_type']) !== ''
    ) {

        $eventType = trim(
            (string)$data['event_type']
        );

        if (mb_strlen($eventType) > 100) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "event_type cannot exceed 100 characters."
            ]);

            exit;
        }

        if (
            preg_match(
                '/\s{2,}/',
                $eventType
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "event_type cannot contain multiple consecutive spaces."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EVENT DATE
    |--------------------------------------------------------------------------
    */

    $eventDate = null;

    if (
        array_key_exists('event_date', $data) &&
        $data['event_date'] !== null &&
        trim((string)$data['event_date']) !== ''
    ) {

        $eventDate = trim(
            (string)$data['event_date']
        );

        $dateObject = DateTime::createFromFormat(
            'Y-m-d',
            $eventDate
        );

        $dateErrors = DateTime::getLastErrors();

        $hasDateErrors = is_array($dateErrors)
            ? (
                $dateErrors['warning_count'] > 0 ||
                $dateErrors['error_count'] > 0
            )
            : false;

        if (
            !$dateObject ||
            $hasDateErrors ||
            $dateObject->format('Y-m-d') !== $eventDate
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "event_date must be in YYYY-MM-DD format."
            ]);

            exit;
        }

        $today = new DateTime(
            date('Y-m-d')
        );

        if ($dateObject < $today) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "event_date cannot be in the past."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EVENT TIME
    |--------------------------------------------------------------------------
    */

    $eventTime = null;

    if (
        array_key_exists('event_time', $data) &&
        $data['event_time'] !== null &&
        trim((string)$data['event_time']) !== ''
    ) {

        $eventTime = trim(
            (string)$data['event_time']
        );

        if (mb_strlen($eventTime) > 100) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "event_time cannot exceed 100 characters."
            ]);

            exit;
        }

        if (
            preg_match(
                '/\s{2,}/',
                $eventTime
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "event_time cannot contain multiple consecutive spaces."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GUEST COUNT
    |--------------------------------------------------------------------------
    */

    $guestCount = null;

    if (
        array_key_exists('guest_count', $data) &&
        $data['guest_count'] !== null &&
        $data['guest_count'] !== ''
    ) {

        if (
            filter_var(
                $data['guest_count'],
                FILTER_VALIDATE_INT
            ) === false ||
            (int)$data['guest_count'] <= 0
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "guest_count must be a positive integer."
            ]);

            exit;
        }

        $guestCount = (int)$data['guest_count'];

        if ($guestCount > 100000) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "guest_count cannot exceed 100000."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FOOD REQUIRED
    |--------------------------------------------------------------------------
    */

    $foodRequired = 0;

    if (array_key_exists('food_required', $data)) {

        $value = $data['food_required'];

        if (is_bool($value)) {

            $foodRequired = $value ? 1 : 0;

        } elseif (
            is_int($value) ||
            is_string($value)
        ) {

            if (
                !in_array(
                    (string)$value,
                    ['0', '1'],
                    true
                )
            ) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" =>
                        "food_required must be 0 or 1."
                ]);

                exit;
            }

            $foodRequired = (int)$value;

        } else {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "food_required must be boolean or 0/1."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DECORATION REQUIRED
    |--------------------------------------------------------------------------
    */

    $decorationRequired = 0;

    if (array_key_exists('decoration_required', $data)) {

        $value = $data['decoration_required'];

        if (is_bool($value)) {

            $decorationRequired = $value ? 1 : 0;

        } elseif (
            is_int($value) ||
            is_string($value)
        ) {

            if (
                !in_array(
                    (string)$value,
                    ['0', '1'],
                    true
                )
            ) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" =>
                        "decoration_required must be 0 or 1."
                ]);

                exit;
            }

            $decorationRequired = (int)$value;

        } else {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "decoration_required must be boolean or 0/1."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OTHER REQUIREMENTS
    |--------------------------------------------------------------------------
    */

    $otherRequirements = null;

    if (
        array_key_exists('other_requirements', $data) &&
        $data['other_requirements'] !== null &&
        trim((string)$data['other_requirements']) !== ''
    ) {

        $otherRequirements = trim(
            (string)$data['other_requirements']
        );

        if (mb_strlen($otherRequirements) > 5000) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "other_requirements cannot exceed 5000 characters."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REQUIRE AT LEAST ONE ENQUIRY DETAIL
    |--------------------------------------------------------------------------
    */

    if (
        $hallId === null &&
        $eventType === null &&
        $eventDate === null &&
        $eventTime === null &&
        $guestCount === null &&
        $foodRequired === 0 &&
        $decorationRequired === 0 &&
        $otherRequirements === null
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "Please provide at least one hall enquiry detail."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | BEGIN TRANSACTION
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | VALIDATE HALL
        |--------------------------------------------------------------------------
        */

        $hall = null;

        if ($hallId !== null) {

            $hallSql = "
                SELECT
                    h.id,
                    h.hall_type_id,
                    h.name,
                    h.slug,
                    h.capacity,
                    h.status,
                    ht.name AS hall_type_name
                FROM halls h
                LEFT JOIN hall_types ht
                    ON ht.id = h.hall_type_id
                WHERE h.id = ?
                LIMIT 1
                FOR UPDATE
            ";

            $hallStmt = $conn->prepare($hallSql);

            if (!$hallStmt) {
                throw new Exception(
                    "Failed to prepare hall validation query: " .
                    $conn->error
                );
            }

            $hallStmt->bind_param(
                "i",
                $hallId
            );

            if (!$hallStmt->execute()) {

                $error = $hallStmt->error;
                $hallStmt->close();

                throw new Exception(
                    "Failed to validate hall: " . $error
                );
            }

            $hallResult = $hallStmt->get_result();
            $hall = $hallResult->fetch_assoc();

            $hallStmt->close();

            if (!$hall) {

                $conn->rollback();

                http_response_code(404);

                echo json_encode([
                    "status" => false,
                    "message" => "Selected hall not found.",
                    "hall_id" => $hallId
                ]);

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | HALL MUST BE AVAILABLE
            |--------------------------------------------------------------------------
            */

            if ($hall['status'] !== 'available') {

                $conn->rollback();

                http_response_code(409);

                echo json_encode([
                    "status" => false,
                    "message" =>
                        "Selected hall is not currently available for enquiry.",
                    "hall_id" => $hallId,
                    "hall_status" => $hall['status']
                ]);

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | GUEST COUNT VS CAPACITY
            |--------------------------------------------------------------------------
            */

            if (
                $guestCount !== null &&
                (int)$hall['capacity'] > 0 &&
                $guestCount > (int)$hall['capacity']
            ) {

                $conn->rollback();

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" =>
                        "Guest count exceeds the selected hall capacity.",
                    "guest_count" => $guestCount,
                    "hall_capacity" => (int)$hall['capacity']
                ]);

                exit;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | GENERATE LEAD REFERENCE
        |--------------------------------------------------------------------------
        */

        $leadReference = null;
        $maxAttempts = 10;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {

            $leadReference =
                'HAL-' .
                date('Ymd') .
                '-' .
                strtoupper(
                    bin2hex(
                        random_bytes(4)
                    )
                );

            $checkReferenceSql = "
                SELECT id
                FROM leads
                WHERE lead_reference = ?
                LIMIT 1
            ";

            $checkReferenceStmt =
                $conn->prepare($checkReferenceSql);

            if (!$checkReferenceStmt) {
                throw new Exception(
                    "Failed to prepare lead reference validation query."
                );
            }

            $checkReferenceStmt->bind_param(
                "s",
                $leadReference
            );

            if (!$checkReferenceStmt->execute()) {

                $error = $checkReferenceStmt->error;
                $checkReferenceStmt->close();

                throw new Exception(
                    "Failed to validate lead reference: " .
                    $error
                );
            }

            $checkReferenceResult =
                $checkReferenceStmt->get_result();

            $referenceExists =
                $checkReferenceResult->fetch_assoc();

            $checkReferenceStmt->close();

            if (!$referenceExists) {
                break;
            }

            if ($attempt === $maxAttempts) {
                throw new Exception(
                    "Unable to generate a unique lead reference."
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT COMMON LEAD
        |--------------------------------------------------------------------------
        */

        $leadSql = "
            INSERT INTO leads (
                lead_reference,
                domain,
                customer_name,
                customer_phone,
                customer_email,
                event_date,
                guest_count,
                preferred_time,
                requirement,
                status,
                estimated_amount,
                converted_amount,
                converted_at,
                source,
                assigned_admin_id
            ) VALUES (
                ?,
                'hall',
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'new',
                NULL,
                NULL,
                NULL,
                'website',
                NULL
            )
        ";

        $leadStmt = $conn->prepare($leadSql);

        if (!$leadStmt) {
            throw new Exception(
                "Failed to prepare lead insert query: " .
                $conn->error
            );
        }

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT:
        | 8 variables:
        |
        | 1. leadReference       s
        | 2. customerName        s
        | 3. customerPhone       s
        | 4. customerEmail       s
        | 5. eventDate           s
        | 6. guestCount          i
        | 7. eventTime           s
        | 8. otherRequirements   s
        |
        | Type sequence:
        | s s s s s i s s
        |--------------------------------------------------------------------------
        */

        $leadBindTypes = 'sssssis' . 's';

        $leadStmt->bind_param(
            $leadBindTypes,
            $leadReference,
            $customerName,
            $customerPhone,
            $customerEmail,
            $eventDate,
            $guestCount,
            $eventTime,
            $otherRequirements
        );

        if (!$leadStmt->execute()) {

            $error = $leadStmt->error;
            $leadStmt->close();

            throw new Exception(
                "Failed to create hall lead: " .
                $error
            );
        }

        $leadId = (int)$conn->insert_id;

        $leadStmt->close();

        if ($leadId <= 0) {
            throw new Exception(
                "Failed to generate lead ID."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT HALL LEAD DETAILS
        |--------------------------------------------------------------------------
        */

        $hallLeadSql = "
            INSERT INTO hall_lead_details (
                lead_id,
                hall_id,
                event_type,
                event_date,
                event_time,
                guest_count,
                food_required,
                decoration_required,
                other_requirements
            ) VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ";

        $hallLeadStmt =
            $conn->prepare($hallLeadSql);

        if (!$hallLeadStmt) {
            throw new Exception(
                "Failed to prepare hall lead details query: " .
                $conn->error
            );
        }

        $hallLeadStmt->bind_param(
            "iisssiiis",
            $leadId,
            $hallId,
            $eventType,
            $eventDate,
            $eventTime,
            $guestCount,
            $foodRequired,
            $decorationRequired,
            $otherRequirements
        );

        if (!$hallLeadStmt->execute()) {

            $error = $hallLeadStmt->error;
            $hallLeadStmt->close();

            throw new Exception(
                "Failed to create hall lead details: " .
                $error
            );
        }

        $hallLeadStmt->close();

        /*
        |--------------------------------------------------------------------------
        | CREATE NOTIFICATION MESSAGE
        |--------------------------------------------------------------------------
        */

        $hallName =
            $hall['name'] ?? 'Hall Enquiry';

        $hallTypeName =
            $hall['hall_type_name'] ?? null;

        $notificationMessage =
            "New Hall Enquiry\n\n" .
            "Lead ID: {$leadId}\n" .
            "Reference: {$leadReference}\n" .
            "Customer: {$customerName}\n" .
            "Phone: {$customerPhone}";

        if ($customerEmail !== null) {

            $notificationMessage .=
                "\nEmail: {$customerEmail}";
        }

        $notificationMessage .=
            "\nHall: {$hallName}";

        if ($hallTypeName !== null) {

            $notificationMessage .=
                "\nHall Type: {$hallTypeName}";
        }

        if ($eventType !== null) {

            $notificationMessage .=
                "\nEvent Type: {$eventType}";
        }

        if ($eventDate !== null) {

            $notificationMessage .=
                "\nEvent Date: {$eventDate}";
        }

        if ($eventTime !== null) {

            $notificationMessage .=
                "\nEvent Time: {$eventTime}";
        }

        if ($guestCount !== null) {

            $notificationMessage .=
                "\nGuests: {$guestCount}";
        }

        $notificationMessage .=
            "\nFood Required: " .
            ($foodRequired ? "Yes" : "No");

        $notificationMessage .=
            "\nDecoration Required: " .
            ($decorationRequired ? "Yes" : "No");

        if ($otherRequirements !== null) {

            $notificationMessage .=
                "\nRequirements: {$otherRequirements}";
        }

        /*
        |--------------------------------------------------------------------------
        | NOTIFICATION RECIPIENTS
        |--------------------------------------------------------------------------
        */

        $whatsappAdminNumber =
            getenv('WHATSAPP_ADMIN_NUMBER');

        $adminEmail =
            getenv('ADMIN_EMAIL');

        /*
        |--------------------------------------------------------------------------
        | WHATSAPP NOTIFICATION
        |--------------------------------------------------------------------------
        */

        $whatsappNotificationId = null;

        if (
            getenv('WHATSAPP_ENABLED') !== false &&
            filter_var(
                getenv('WHATSAPP_ENABLED'),
                FILTER_VALIDATE_BOOLEAN
            ) === true &&
            $whatsappAdminNumber !== false &&
            trim($whatsappAdminNumber) !== ''
        ) {

            $whatsappRecipient =
                trim($whatsappAdminNumber);

            $notificationSql = "
                INSERT INTO lead_notifications (
                    lead_id,
                    channel,
                    recipient,
                    message,
                    status
                ) VALUES (
                    ?,
                    'whatsapp',
                    ?,
                    ?,
                    'pending'
                )
            ";

            $notificationStmt =
                $conn->prepare($notificationSql);

            if (!$notificationStmt) {
                throw new Exception(
                    "Failed to prepare WhatsApp notification query: " .
                    $conn->error
                );
            }

            $notificationStmt->bind_param(
                "iss",
                $leadId,
                $whatsappRecipient,
                $notificationMessage
            );

            if (!$notificationStmt->execute()) {

                $error = $notificationStmt->error;
                $notificationStmt->close();

                throw new Exception(
                    "Failed to create WhatsApp notification: " .
                    $error
                );
            }

            $whatsappNotificationId =
                (int)$conn->insert_id;

            $notificationStmt->close();
        }

        /*
        |--------------------------------------------------------------------------
        | EMAIL NOTIFICATION
        |--------------------------------------------------------------------------
        */

        $emailNotificationId = null;

        if (
            $adminEmail !== false &&
            trim($adminEmail) !== ''
        ) {

            $adminEmail =
                strtolower(
                    trim($adminEmail)
                );

            if (
                filter_var(
                    $adminEmail,
                    FILTER_VALIDATE_EMAIL
                )
            ) {

                $emailSubject =
                    "New Hall Enquiry - {$leadReference}";

                $emailMessage =
                    $notificationMessage .
                    "\n\nPlease check the admin dashboard for complete enquiry details.";

                $emailNotificationSql = "
                    INSERT INTO lead_notifications (
                        lead_id,
                        channel,
                        recipient,
                        message,
                        status
                    ) VALUES (
                        ?,
                        'email',
                        ?,
                        ?,
                        'pending'
                    )
                ";

                $emailNotificationStmt =
                    $conn->prepare(
                        $emailNotificationSql
                    );

                if (!$emailNotificationStmt) {
                    throw new Exception(
                        "Failed to prepare email notification query: " .
                        $conn->error
                    );
                }

                $emailMessage =
                    "Subject: {$emailSubject}\n\n" .
                    $emailMessage;

                $emailNotificationStmt->bind_param(
                    "iss",
                    $leadId,
                    $adminEmail,
                    $emailMessage
                );

                if (!$emailNotificationStmt->execute()) {

                    $error =
                        $emailNotificationStmt->error;

                    $emailNotificationStmt->close();

                    throw new Exception(
                        "Failed to create email notification: " .
                        $error
                    );
                }

                $emailNotificationId =
                    (int)$conn->insert_id;

                $emailNotificationStmt->close();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFY CREATED LEAD
        |--------------------------------------------------------------------------
        */

        $verifyLeadSql = "
            SELECT
                l.id,
                l.lead_reference,
                l.domain,
                l.customer_name,
                l.customer_phone,
                l.customer_email,
                l.event_date,
                l.guest_count,
                l.preferred_time,
                l.requirement,
                l.status,
                l.source,
                l.assigned_admin_id,
                l.created_at,
                l.updated_at
            FROM leads l
            WHERE l.id = ?
            LIMIT 1
        ";

        $verifyLeadStmt =
            $conn->prepare($verifyLeadSql);

        if (!$verifyLeadStmt) {
            throw new Exception(
                "Failed to prepare lead verification query."
            );
        }

        $verifyLeadStmt->bind_param(
            "i",
            $leadId
        );

        if (!$verifyLeadStmt->execute()) {

            $error = $verifyLeadStmt->error;
            $verifyLeadStmt->close();

            throw new Exception(
                "Failed to verify created lead: " .
                $error
            );
        }

        $verifyLeadResult =
            $verifyLeadStmt->get_result();

        $createdLead =
            $verifyLeadResult->fetch_assoc();

        $verifyLeadStmt->close();

        if (!$createdLead) {
            throw new Exception(
                "Created lead could not be verified."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $conn->commit();

        /*
        |--------------------------------------------------------------------------
        | FINAL RESPONSE
        |--------------------------------------------------------------------------
        */

        http_response_code(201);

        echo json_encode([
            "status" => true,
            "message" =>
                "Hall enquiry created successfully.",

            "data" => [

                "lead" => [
                    "id" =>
                        (int)$createdLead['id'],

                    "lead_reference" =>
                        $createdLead['lead_reference'],

                    "domain" =>
                        $createdLead['domain'],

                    "status" =>
                        $createdLead['status'],

                    "source" =>
                        $createdLead['source'],

                    "assigned_admin_id" =>
                        $createdLead['assigned_admin_id'] !== null
                            ? (int)$createdLead['assigned_admin_id']
                            : null
                ],

                "customer" => [
                    "name" =>
                        $createdLead['customer_name'],

                    "phone" =>
                        $createdLead['customer_phone'],

                    "email" =>
                        $createdLead['customer_email']
                ],

                "hall" => [
                    "id" =>
                        $hallId,

                    "name" =>
                        $hall['name'] ?? null,

                    "hall_type" =>
                        $hall['hall_type_name'] ?? null,

                    "capacity" =>
                        $hall !== null
                            ? (int)$hall['capacity']
                            : null
                ],

                "event" => [
                    "type" =>
                        $eventType,

                    "date" =>
                        $eventDate,

                    "time" =>
                        $eventTime,

                    "guest_count" =>
                        $guestCount
                ],

                "requirements" => [
                    "food_required" =>
                        (bool)$foodRequired,

                    "decoration_required" =>
                        (bool)$decorationRequired,

                    "other_requirements" =>
                        $otherRequirements
                ],

                "notifications" => [

                    "whatsapp" => [
                        "created" =>
                            $whatsappNotificationId !== null,

                        "notification_id" =>
                            $whatsappNotificationId
                    ],

                    "email" => [
                        "created" =>
                            $emailNotificationId !== null,

                        "notification_id" =>
                            $emailNotificationId
                    ]
                ],

                "created_at" =>
                    $createdLead['created_at']
            ]
        ]);

        exit;

    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | ROLLBACK
        |--------------------------------------------------------------------------
        */

        $conn->rollback();

        throw $e;
    }

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" =>
            "Failed to create hall enquiry.",
        "error" =>
            $e->getMessage()
    ]);

    exit;
}