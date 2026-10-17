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
    'id',
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
| ID
|--------------------------------------------------------------------------
*/

if(!array_key_exists('id',$data)){
    sendResponse(
        false,
        'id is required.',
        null,
        422
    );
}

if(
    $data['id']===null||
    is_array($data['id'])||
    is_object($data['id'])||
    is_bool($data['id'])
){
    sendResponse(
        false,
        'id must be a valid positive integer.',
        null,
        422
    );
}

$idInput=trim((string)$data['id']);

if(
    $idInput===''||
    !preg_match('/^[1-9][0-9]*$/',$idInput)
){
    sendResponse(
        false,
        'id must be a valid positive integer.',
        null,
        422
    );
}

$id=(int)$idInput;

/*
|--------------------------------------------------------------------------
| AT LEAST ONE UPDATE FIELD
|--------------------------------------------------------------------------
*/

$updateFields=[
    'variant_id',
    'instagram_url',
    'link_title',
    'is_primary',
    'status'
];

$hasUpdateField=false;

foreach($updateFields as $field){
    if(array_key_exists($field,$data)){
        $hasUpdateField=true;
        break;
    }
}

if(!$hasUpdateField){
    sendResponse(
        false,
        'At least one field must be provided for update.',
        [
            'updatable_fields'=>$updateFields
        ],
        422
    );
}

/*
|--------------------------------------------------------------------------
| VARIANT ID
|--------------------------------------------------------------------------
*/

$variantId=null;

if(array_key_exists('variant_id',$data)){

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
}

/*
|--------------------------------------------------------------------------
| INSTAGRAM URL
|--------------------------------------------------------------------------
*/

$instagramUrl=null;

if(array_key_exists('instagram_url',$data)){

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
            'instagram_url cannot be empty.',
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

    if($instagramHost!=='instagram.com'){
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
}

/*
|--------------------------------------------------------------------------
| LINK TITLE
|--------------------------------------------------------------------------
*/

$linkTitle=null;
$linkTitleProvided=false;

if(array_key_exists('link_title',$data)){

    $linkTitleProvided=true;

    if(
        is_array($data['link_title'])||
        is_object($data['link_title'])||
        is_bool($data['link_title'])
    ){
        sendResponse(
            false,
            'link_title must be a valid string or null.',
            null,
            422
        );
    }

    if($data['link_title']===null){

        $linkTitle=null;

    }else{

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

$isPrimary=null;

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

$status=null;

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

try{

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | FETCH CURRENT LINK
    |--------------------------------------------------------------------------
    */

    $currentStmt=$pdo->prepare(
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
         WHERE id=:id
         LIMIT 1
         FOR UPDATE"
    );

    $currentStmt->bindValue(
        ':id',
        $id,
        PDO::PARAM_INT
    );

    $currentStmt->execute();

    $current=$currentStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if(!$current){

        $pdo->rollBack();

        sendResponse(
            false,
            'Instagram link not found.',
            [
                'id'=>$id
            ],
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL VALUES
    |--------------------------------------------------------------------------
    */

    $finalVariantId=
        $variantId!==null
            ?$variantId
            :(int)$current['variant_id'];

    $finalInstagramUrl=
        $instagramUrl!==null
            ?$instagramUrl
            :$current['instagram_url'];

    $finalLinkTitle=
        $linkTitleProvided
            ?$linkTitle
            :$current['link_title'];

    $finalIsPrimary=
        $isPrimary!==null
            ?$isPrimary
            :(int)$current['is_primary'];

    $finalStatus=
        $status!==null
            ?$status
            :$current['status'];

    /*
    |--------------------------------------------------------------------------
    | CHECK VARIANT EXISTS
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
        $finalVariantId,
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
                'variant_id'=>$finalVariantId
            ],
            404
        );
    }

    if(
        $variant['product_id']===null||
        $variant['product_name']===null
    ){

        $pdo->rollBack();

        sendResponse(
            false,
            'Product linked to this variant was not found.',
            [
                'variant_id'=>$finalVariantId
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRIMARY MUST BE ACTIVE
    |--------------------------------------------------------------------------
    */

    if(
        $finalIsPrimary===1&&
        $finalStatus!=='active'
    ){

        $pdo->rollBack();

        sendResponse(
            false,
            'Primary Instagram link must be active.',
            null,
            422
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
            instagram_url
         FROM product_variant_instagram_links
         WHERE variant_id=:variant_id
         AND instagram_url=:instagram_url
         AND id<>:id
         LIMIT 1
         FOR UPDATE"
    );

    $duplicateStmt->bindValue(
        ':variant_id',
        $finalVariantId,
        PDO::PARAM_INT
    );

    $duplicateStmt->bindValue(
        ':instagram_url',
        $finalInstagramUrl,
        PDO::PARAM_STR
    );

    $duplicateStmt->bindValue(
        ':id',
        $id,
        PDO::PARAM_INT
    );

    $duplicateStmt->execute();

    $duplicate=$duplicateStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if($duplicate){

        $pdo->rollBack();

        sendResponse(
            false,
            'This Instagram link is already added for this product variant.',
            [
                'existing_link'=>[
                    'id'=>
                        (int)$duplicate['id'],

                    'variant_id'=>
                        (int)$duplicate['variant_id'],

                    'instagram_url'=>
                        $duplicate['instagram_url']
                ]
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE OLD PRIMARY
    |--------------------------------------------------------------------------
    */

    if($finalIsPrimary===1){

        $removePrimaryStmt=$pdo->prepare(
            "UPDATE product_variant_instagram_links
             SET is_primary=0
             WHERE variant_id=:variant_id
             AND id<>:id
             AND is_primary=1"
        );

        $removePrimaryStmt->bindValue(
            ':variant_id',
            $finalVariantId,
            PDO::PARAM_INT
        );

        $removePrimaryStmt->bindValue(
            ':id',
            $id,
            PDO::PARAM_INT
        );

        $removePrimaryStmt->execute();
    }

    /*
    |--------------------------------------------------------------------------
    | IF VARIANT CHANGED REMOVE OLD PRIMARY ISSUE
    |--------------------------------------------------------------------------
    */

    $oldVariantId=(int)$current['variant_id'];

    if(
        $oldVariantId!==$finalVariantId&&
        (int)$current['is_primary']===1
    ){

        $oldPrimaryStmt=$pdo->prepare(
            "SELECT
                id
             FROM product_variant_instagram_links
             WHERE variant_id=:variant_id
             AND id<>:id
             AND status='active'
             ORDER BY id ASC
             LIMIT 1
             FOR UPDATE"
        );

        $oldPrimaryStmt->bindValue(
            ':variant_id',
            $oldVariantId,
            PDO::PARAM_INT
        );

        $oldPrimaryStmt->bindValue(
            ':id',
            $id,
            PDO::PARAM_INT
        );

        $oldPrimaryStmt->execute();

        $oldReplacementId=$oldPrimaryStmt->fetchColumn();

        if($oldReplacementId!==false){

            $setOldPrimaryStmt=$pdo->prepare(
                "UPDATE product_variant_instagram_links
                 SET is_primary=1
                 WHERE id=:id"
            );

            $setOldPrimaryStmt->bindValue(
                ':id',
                (int)$oldReplacementId,
                PDO::PARAM_INT
            );

            $setOldPrimaryStmt->execute();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE LINK
    |--------------------------------------------------------------------------
    */

    $updateStmt=$pdo->prepare(
        "UPDATE product_variant_instagram_links
         SET
            variant_id=:variant_id,
            instagram_url=:instagram_url,
            link_title=:link_title,
            is_primary=:is_primary,
            status=:status
         WHERE id=:id"
    );

    $updateStmt->bindValue(
        ':variant_id',
        $finalVariantId,
        PDO::PARAM_INT
    );

    $updateStmt->bindValue(
        ':instagram_url',
        $finalInstagramUrl,
        PDO::PARAM_STR
    );

    if($finalLinkTitle===null){
        $updateStmt->bindValue(
            ':link_title',
            null,
            PDO::PARAM_NULL
        );
    }else{
        $updateStmt->bindValue(
            ':link_title',
            $finalLinkTitle,
            PDO::PARAM_STR
        );
    }

    $updateStmt->bindValue(
        ':is_primary',
        $finalIsPrimary,
        PDO::PARAM_INT
    );

    $updateStmt->bindValue(
        ':status',
        $finalStatus,
        PDO::PARAM_STR
    );

    $updateStmt->bindValue(
        ':id',
        $id,
        PDO::PARAM_INT
    );

    $updateStmt->execute();

    /*
    |--------------------------------------------------------------------------
    | PRIMARY REMOVED - AUTO ASSIGN ANOTHER ACTIVE LINK
    |--------------------------------------------------------------------------
    */

    if(
        (int)$current['is_primary']===1&&
        $finalIsPrimary===0&&
        $oldVariantId===$finalVariantId
    ){

        $replacementStmt=$pdo->prepare(
            "SELECT id
             FROM product_variant_instagram_links
             WHERE variant_id=:variant_id
             AND id<>:id
             AND status='active'
             ORDER BY id ASC
             LIMIT 1
             FOR UPDATE"
        );

        $replacementStmt->bindValue(
            ':variant_id',
            $finalVariantId,
            PDO::PARAM_INT
        );

        $replacementStmt->bindValue(
            ':id',
            $id,
            PDO::PARAM_INT
        );

        $replacementStmt->execute();

        $replacementId=$replacementStmt->fetchColumn();

        if($replacementId!==false){

            $setPrimaryStmt=$pdo->prepare(
                "UPDATE product_variant_instagram_links
                 SET is_primary=1
                 WHERE id=:id"
            );

            $setPrimaryStmt->bindValue(
                ':id',
                (int)$replacementId,
                PDO::PARAM_INT
            );

            $setPrimaryStmt->execute();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH UPDATED RECORD
    |--------------------------------------------------------------------------
    */

    $updatedStmt=$pdo->prepare(
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

    $updatedStmt->bindValue(
        ':id',
        $id,
        PDO::PARAM_INT
    );

    $updatedStmt->execute();

    $updated=$updatedStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if(!$updated){

        $pdo->rollBack();

        sendResponse(
            false,
            'Unable to retrieve updated Instagram link.',
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
        'Instagram link updated successfully.',
        [
            'instagram_link'=>[
                'id'=>
                    (int)$updated['id'],

                'variant_id'=>
                    (int)$updated['variant_id'],

                'instagram_url'=>
                    $updated['instagram_url'],

                'link_title'=>
                    $updated['link_title'],

                'is_primary'=>
                    (int)$updated['is_primary'],

                'status'=>
                    $updated['status'],

                'created_by'=>
                    $updated['created_by']!==null
                        ?(int)$updated['created_by']
                        :null,

                'created_by_admin'=>
                    $updated['created_by']!==null
                        ?[
                            'id'=>
                                (int)$updated['created_by'],

                            'name'=>
                                $updated['created_by_admin_name'],

                            'email'=>
                                $updated['created_by_admin_email']
                        ]
                        :null,

                'created_at'=>
                    $updated['created_at'],

                'updated_at'=>
                    $updated['updated_at']
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
        200
    );

}catch(PDOException $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    if((string)$e->getCode()==='23000'){
        sendResponse(
            false,
            'Unable to update Instagram link because of a duplicate or database constraint.',
            [
                'id'=>$id
            ],
            409
        );
    }

    sendResponse(
        false,
        'Unable to update Instagram link.',
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
        'An unexpected error occurred while updating Instagram link.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );
}