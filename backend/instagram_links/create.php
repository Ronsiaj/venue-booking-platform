<?php
declare(strict_types=1);

require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../config/jwt.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if($_SERVER['REQUEST_METHOD']==='OPTIONS'){
    http_response_code(204);
    exit;
}

if($_SERVER['REQUEST_METHOD']!=='POST'){
    sendResponse(
        false,
        'Only POST method is allowed.',
        null,
        405
    );
}

/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
|--------------------------------------------------------------------------
*/

$decoded=authenticate();
validateJWTData($decoded);

if(getAuthenticatedType($decoded)!=='admin'){
    sendResponse(
        false,
        'Admin access only.',
        null,
        403
    );
}

$adminAuth=authenticateAdmin();
checkAdminRole($adminAuth,['admin']);

$adminId=getAuthenticatedId($adminAuth);

if($adminId<=0){
    sendResponse(
        false,
        'Invalid authenticated admin.',
        null,
        401
    );
}

/*
|--------------------------------------------------------------------------
| CONTENT TYPE
|--------------------------------------------------------------------------
*/

$contentType=$_SERVER['CONTENT_TYPE']??'';

if(stripos($contentType,'application/json')===false){
    sendResponse(
        false,
        'Content-Type must be application/json.',
        null,
        415
    );
}

/*
|--------------------------------------------------------------------------
| JSON BODY
|--------------------------------------------------------------------------
*/

$rawBody=file_get_contents('php://input');

if($rawBody===false||trim($rawBody)===''){
    sendResponse(
        false,
        'Request body is required.',
        null,
        400
    );
}

$data=json_decode($rawBody,true);

if(
    json_last_error()!==JSON_ERROR_NONE||
    !is_array($data)
){
    sendResponse(
        false,
        'Invalid JSON body.',
        null,
        400
    );
}

/*
|--------------------------------------------------------------------------
| ALLOWED FIELDS
|--------------------------------------------------------------------------
*/

$allowedFields=[
    'variant_id',
    'instagram_url',
    'link_title',
    'is_primary',
    'status'
];

foreach(array_keys($data) as $field){
    if(!in_array($field,$allowedFields,true)){
        sendResponse(
            false,
            "Invalid field: {$field}.",
            [
                'allowed_fields'=>$allowedFields
            ],
            422
        );
    }
}

/*
|--------------------------------------------------------------------------
| VARIANT ID
|--------------------------------------------------------------------------
*/

if(!array_key_exists('variant_id',$data)){
    sendResponse(
        false,
        'variant_id is required.',
        null,
        422
    );
}

if(
    $data['variant_id']===null||
    is_array($data['variant_id'])||
    is_object($data['variant_id'])||
    is_bool($data['variant_id'])
){
    sendResponse(
        false,
        'variant_id must be a valid positive integer.',
        null,
        422
    );
}

$variantIdInput=trim(
    (string)$data['variant_id']
);

if(
    $variantIdInput===''||
    !preg_match('/^[1-9][0-9]*$/',$variantIdInput)
){
    sendResponse(
        false,
        'variant_id must be a valid positive integer.',
        null,
        422
    );
}

$variantId=(int)$variantIdInput;

/*
|--------------------------------------------------------------------------
| INSTAGRAM URL
|--------------------------------------------------------------------------
*/

if(!array_key_exists('instagram_url',$data)){
    sendResponse(
        false,
        'instagram_url is required.',
        null,
        422
    );
}

if(
    $data['instagram_url']===null||
    is_array($data['instagram_url'])||
    is_object($data['instagram_url'])||
    is_bool($data['instagram_url'])
){
    sendResponse(
        false,
        'instagram_url must be a valid Instagram URL.',
        null,
        422
    );
}

$instagramUrl=trim(
    (string)$data['instagram_url']
);

if($instagramUrl===''){
    sendResponse(
        false,
        'instagram_url is required.',
        null,
        422
    );
}

if(strlen($instagramUrl)>500){
    sendResponse(
        false,
        'instagram_url must not exceed 500 characters.',
        null,
        422
    );
}

if(filter_var($instagramUrl,FILTER_VALIDATE_URL)===false){
    sendResponse(
        false,
        'instagram_url must be a valid URL.',
        null,
        422
    );
}

$parsedUrl=parse_url($instagramUrl);

$instagramHost=strtolower(
    $parsedUrl['host']??''
);

if(str_starts_with($instagramHost,'www.')){
    $instagramHost=substr(
        $instagramHost,
        4
    );
}

$allowedInstagramHosts=[
    'instagram.com'
];

if(!in_array($instagramHost,$allowedInstagramHosts,true)){
    sendResponse(
        false,
        'Only Instagram URLs are allowed.',
        [
            'allowed_domain'=>'instagram.com'
        ],
        422
    );
}

if(
    !isset($parsedUrl['scheme'])||
    strtolower($parsedUrl['scheme'])!=='https'
){
    sendResponse(
        false,
        'Instagram URL must use HTTPS.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| LINK TITLE
|--------------------------------------------------------------------------
*/

$linkTitle=null;

if(array_key_exists('link_title',$data)){

    if(
        is_array($data['link_title'])||
        is_object($data['link_title'])||
        is_bool($data['link_title'])
    ){
        sendResponse(
            false,
            'link_title must be a valid string.',
            null,
            422
        );
    }

    if($data['link_title']!==null){

        $linkTitle=trim(
            (string)$data['link_title']
        );

        if($linkTitle===''){
            $linkTitle=null;
        }

        if(
            $linkTitle!==null&&
            mb_strlen($linkTitle)>150
        ){
            sendResponse(
                false,
                'link_title must not exceed 150 characters.',
                null,
                422
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| IS PRIMARY
|--------------------------------------------------------------------------
*/

$isPrimary=0;

if(array_key_exists('is_primary',$data)){

    $primaryValue=$data['is_primary'];

    if(is_bool($primaryValue)){
        $isPrimary=$primaryValue?1:0;
    }else{

        if(
            $primaryValue===null||
            is_array($primaryValue)||
            is_object($primaryValue)
        ){
            sendResponse(
                false,
                'is_primary must be 0 or 1.',
                null,
                422
            );
        }

        $primaryInput=trim(
            (string)$primaryValue
        );

        if(!in_array($primaryInput,['0','1'],true)){
            sendResponse(
                false,
                'is_primary must be 0 or 1.',
                [
                    'allowed_values'=>[
                        0,
                        1
                    ]
                ],
                422
            );
        }

        $isPrimary=(int)$primaryInput;
    }
}

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$status='active';

if(array_key_exists('status',$data)){

    if(
        $data['status']===null||
        is_array($data['status'])||
        is_object($data['status'])||
        is_bool($data['status'])
    ){
        sendResponse(
            false,
            'Invalid status.',
            null,
            422
        );
    }

    $status=strtolower(
        trim((string)$data['status'])
    );

    if(
        !in_array(
            $status,
            [
                'active',
                'inactive'
            ],
            true
        )
    ){
        sendResponse(
            false,
            'Invalid status.',
            [
                'allowed_values'=>[
                    'active',
                    'inactive'
                ]
            ],
            422
        );
    }
}

/*
|--------------------------------------------------------------------------
| PRIMARY LINK VALIDATION
|--------------------------------------------------------------------------
*/

if($isPrimary===1&&$status!=='active'){
    sendResponse(
        false,
        'Primary Instagram link must be active.',
        null,
        422
    );
}

try{

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | CHECK PRODUCT VARIANT
    |--------------------------------------------------------------------------
    */

    $variantStmt=$pdo->prepare(
        "SELECT
            pv.id,
            pv.product_id,
            pv.size_id,
            pv.color_id,
            pv.sku,
            pv.variant_name,
            pv.original_price,
            pv.discount_type,
            pv.discount_value,
            pv.selling_price,
            pv.gst_rate,
            pv.gst_amount,
            pv.price_with_tax,
            pv.stock_quantity,
            pv.reserved_quantity,
            pv.low_stock_limit,
            pv.is_available,
            pv.created_at,
            pv.updated_at,
            p.name AS product_name,
            p.slug AS product_slug,
            p.description AS product_description,
            p.status AS product_status,
            s.name AS size_name,
            s.status AS size_status,
            c.name AS color_name,
            c.hex_code AS color_hex_code,
            c.status AS color_status
         FROM product_variants pv
         LEFT JOIN products p
            ON p.id=pv.product_id
         LEFT JOIN sizes s
            ON s.id=pv.size_id
         LEFT JOIN colors c
            ON c.id=pv.color_id
         WHERE pv.id=:variant_id
         LIMIT 1
         FOR UPDATE"
    );

    $variantStmt->bindValue(
        ':variant_id',
        $variantId,
        PDO::PARAM_INT
    );

    $variantStmt->execute();

    $variant=$variantStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if(!$variant){

        $pdo->rollBack();

        sendResponse(
            false,
            'Product variant not found.',
            [
                'variant_id'=>$variantId
            ],
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT VALIDATION
    |--------------------------------------------------------------------------
    */

    if(
        $variant['product_id']===null||
        $variant['product_name']===null
    ){

        $pdo->rollBack();

        sendResponse(
            false,
            'Product linked to this variant was not found.',
            [
                'variant_id'=>$variantId
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE URL CHECK
    |--------------------------------------------------------------------------
    */

    $duplicateStmt=$pdo->prepare(
        "SELECT
            id,
            variant_id,
            instagram_url,
            link_title,
            is_primary,
            status,
            created_by,
            created_at,
            updated_at
         FROM product_variant_instagram_links
         WHERE variant_id=:variant_id
         AND instagram_url=:instagram_url
         LIMIT 1
         FOR UPDATE"
    );

    $duplicateStmt->bindValue(
        ':variant_id',
        $variantId,
        PDO::PARAM_INT
    );

    $duplicateStmt->bindValue(
        ':instagram_url',
        $instagramUrl,
        PDO::PARAM_STR
    );

    $duplicateStmt->execute();

    $existing=$duplicateStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if($existing){

        $pdo->rollBack();

        sendResponse(
            false,
            'This Instagram link is already added for this product variant.',
            [
                'existing_link'=>[
                    'id'=>
                        (int)$existing['id'],

                    'variant_id'=>
                        (int)$existing['variant_id'],

                    'instagram_url'=>
                        $existing['instagram_url'],

                    'link_title'=>
                        $existing['link_title'],

                    'is_primary'=>
                        (int)$existing['is_primary'],

                    'status'=>
                        $existing['status'],

                    'created_by'=>
                        $existing['created_by']!==null
                            ?(int)$existing['created_by']
                            :null,

                    'created_at'=>
                        $existing['created_at'],

                    'updated_at'=>
                        $existing['updated_at']
                ]
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK EXISTING LINKS
    |--------------------------------------------------------------------------
    */

    $countStmt=$pdo->prepare(
        "SELECT COUNT(*)
         FROM product_variant_instagram_links
         WHERE variant_id=:variant_id"
    );

    $countStmt->bindValue(
        ':variant_id',
        $variantId,
        PDO::PARAM_INT
    );

    $countStmt->execute();

    $existingLinkCount=(int)$countStmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | FIRST LINK AUTO PRIMARY
    |--------------------------------------------------------------------------
    */

    if(
        $existingLinkCount===0&&
        $status==='active'
    ){
        $isPrimary=1;
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE OLD PRIMARY
    |--------------------------------------------------------------------------
    */

    if($isPrimary===1){

        $removePrimaryStmt=$pdo->prepare(
            "UPDATE product_variant_instagram_links
             SET is_primary=0
             WHERE variant_id=:variant_id
             AND is_primary=1"
        );

        $removePrimaryStmt->bindValue(
            ':variant_id',
            $variantId,
            PDO::PARAM_INT
        );

        $removePrimaryStmt->execute();
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT INSTAGRAM LINK
    |--------------------------------------------------------------------------
    */

    $insertStmt=$pdo->prepare(
        "INSERT INTO product_variant_instagram_links(
            variant_id,
            instagram_url,
            link_title,
            is_primary,
            status,
            created_by
         )VALUES(
            :variant_id,
            :instagram_url,
            :link_title,
            :is_primary,
            :status,
            :created_by
         )"
    );

    $insertStmt->bindValue(
        ':variant_id',
        $variantId,
        PDO::PARAM_INT
    );

    $insertStmt->bindValue(
        ':instagram_url',
        $instagramUrl,
        PDO::PARAM_STR
    );

    if($linkTitle===null){
        $insertStmt->bindValue(
            ':link_title',
            null,
            PDO::PARAM_NULL
        );
    }else{
        $insertStmt->bindValue(
            ':link_title',
            $linkTitle,
            PDO::PARAM_STR
        );
    }

    $insertStmt->bindValue(
        ':is_primary',
        $isPrimary,
        PDO::PARAM_INT
    );

    $insertStmt->bindValue(
        ':status',
        $status,
        PDO::PARAM_STR
    );

    $insertStmt->bindValue(
        ':created_by',
        $adminId,
        PDO::PARAM_INT
    );

    $insertStmt->execute();

    $instagramLinkId=(int)$pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | FETCH CREATED LINK
    |--------------------------------------------------------------------------
    */

    $createdStmt=$pdo->prepare(
        "SELECT
            pil.id,
            pil.variant_id,
            pil.instagram_url,
            pil.link_title,
            pil.is_primary,
            pil.status,
            pil.created_by,
            pil.created_at,
            pil.updated_at,
            a.name AS created_by_admin_name,
            a.email AS created_by_admin_email
         FROM product_variant_instagram_links pil
         LEFT JOIN admins a
            ON a.id=pil.created_by
         WHERE pil.id=:id
         LIMIT 1"
    );

    $createdStmt->bindValue(
        ':id',
        $instagramLinkId,
        PDO::PARAM_INT
    );

    $createdStmt->execute();

    $created=$createdStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if(!$created){

        $pdo->rollBack();

        sendResponse(
            false,
            'Unable to retrieve created Instagram link.',
            null,
            500
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STOCK DETAILS
    |--------------------------------------------------------------------------
    */

    $stockQuantity=
        (int)$variant['stock_quantity'];

    $reservedQuantity=
        (int)$variant['reserved_quantity'];

    $availableQuantity=max(
        0,
        $stockQuantity-$reservedQuantity
    );

    $lowStockLimit=
        (int)$variant['low_stock_limit'];

    if($availableQuantity<=0){

        $stockStatus='out_of_stock';

    }elseif(
        $availableQuantity<=$lowStockLimit
    ){

        $stockStatus='low_stock';

    }else{

        $stockStatus='in_stock';
    }

    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'Instagram link created successfully.',
        [
            'instagram_link'=>[
                'id'=>
                    (int)$created['id'],

                'variant_id'=>
                    (int)$created['variant_id'],

                'instagram_url'=>
                    $created['instagram_url'],

                'link_title'=>
                    $created['link_title'],

                'is_primary'=>
                    (int)$created['is_primary'],

                'status'=>
                    $created['status'],

                'created_by'=>
                    $created['created_by']!==null
                        ?(int)$created['created_by']
                        :null,

                'created_by_admin'=>
                    $created['created_by']!==null
                        ?[
                            'id'=>
                                (int)$created['created_by'],

                            'name'=>
                                $created['created_by_admin_name'],

                            'email'=>
                                $created['created_by_admin_email']
                        ]
                        :null,

                'created_at'=>
                    $created['created_at'],

                'updated_at'=>
                    $created['updated_at']
            ],

            'product'=>[
                'id'=>
                    (int)$variant['product_id'],

                'name'=>
                    $variant['product_name'],

                'slug'=>
                    $variant['product_slug'],

                'description'=>
                    $variant['product_description'],

                'status'=>
                    $variant['product_status']
            ],

            'product_variant'=>[
                'id'=>
                    (int)$variant['id'],

                'product_id'=>
                    (int)$variant['product_id'],

                'sku'=>
                    $variant['sku'],

                'variant_name'=>
                    $variant['variant_name'],

                'size'=>
                    $variant['size_id']!==null
                        ?[
                            'id'=>
                                (int)$variant['size_id'],

                            'name'=>
                                $variant['size_name'],

                            'status'=>
                                $variant['size_status']
                        ]
                        :null,

                'color'=>
                    $variant['color_id']!==null
                        ?[
                            'id'=>
                                (int)$variant['color_id'],

                            'name'=>
                                $variant['color_name'],

                            'hex_code'=>
                                $variant['color_hex_code'],

                            'status'=>
                                $variant['color_status']
                        ]
                        :null,

                'pricing'=>[
                    'original_price'=>
                        $variant['original_price'],

                    'discount_type'=>
                        $variant['discount_type'],

                    'discount_value'=>
                        $variant['discount_value'],

                    'selling_price'=>
                        $variant['selling_price'],

                    'gst_rate'=>
                        $variant['gst_rate'],

                    'gst_amount'=>
                        $variant['gst_amount'],

                    'price_with_tax'=>
                        $variant['price_with_tax']
                ],

                'stock'=>[
                    'stock_quantity'=>
                        $stockQuantity,

                    'reserved_quantity'=>
                        $reservedQuantity,

                    'available_quantity'=>
                        $availableQuantity,

                    'low_stock_limit'=>
                        $lowStockLimit,

                    'stock_status'=>
                        $stockStatus
                ],

                'is_available'=>
                    (int)$variant['is_available'],

                'created_at'=>
                    $variant['created_at'],

                'updated_at'=>
                    $variant['updated_at']
            ]
        ],
        201
    );

}catch(PDOException $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    if((string)$e->getCode()==='23000'){

        sendResponse(
            false,
            'Unable to create Instagram link because of a duplicate or database constraint.',
            [
                'variant_id'=>$variantId
            ],
            409
        );
    }

    sendResponse(
        false,
        'Unable to create Instagram link.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );

}catch(Throwable $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'An unexpected error occurred while creating Instagram link.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );
}