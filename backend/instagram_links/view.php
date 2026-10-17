<?php
declare(strict_types=1);

require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../config/jwt.php';

if(!function_exists('sendResponse')){
    function sendResponse(
        bool $success,
        string $message,
        mixed $data=null,
        int $statusCode=200
    ):never{
        http_response_code($statusCode);

        echo json_encode(
            [
                'success'=>$success,
                'message'=>$message,
                'data'=>$data
            ],
            JSON_UNESCAPED_UNICODE|
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if($_SERVER['REQUEST_METHOD']==='OPTIONS'){
    http_response_code(204);
    exit;
}

if($_SERVER['REQUEST_METHOD']!=='GET'){
    sendResponse(
        false,
        'Only GET method is allowed.',
        null,
        405
    );
}

/*
|--------------------------------------------------------------------------
| OPTIONAL AUTHENTICATION
|--------------------------------------------------------------------------
|
| Public -> active only
| User   -> active only
| Admin  -> active + inactive
|
*/

$accessType='public';
$authenticatedId=null;
$authorizationHeader='';

if(isset($_SERVER['HTTP_AUTHORIZATION'])){
    $authorizationHeader=trim(
        (string)$_SERVER['HTTP_AUTHORIZATION']
    );
}

if(
    $authorizationHeader===''&&
    isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])
){
    $authorizationHeader=trim(
        (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    );
}

if(
    $authorizationHeader===''&&
    function_exists('getallheaders')
){
    $headers=getallheaders();

    if(is_array($headers)){
        foreach($headers as $key=>$value){
            if(strtolower((string)$key)==='authorization'){
                $authorizationHeader=trim(
                    (string)$value
                );
                break;
            }
        }
    }
}

if(
    $authorizationHeader===''&&
    function_exists('apache_request_headers')
){
    $headers=apache_request_headers();

    if(is_array($headers)){
        foreach($headers as $key=>$value){
            if(strtolower((string)$key)==='authorization'){
                $authorizationHeader=trim(
                    (string)$value
                );
                break;
            }
        }
    }
}

if($authorizationHeader!==''){

    if(
        !preg_match(
            '/^Bearer\s+(.+)$/i',
            $authorizationHeader
        )
    ){
        sendResponse(
            false,
            'Invalid Authorization header format.',
            null,
            401
        );
    }

    $decoded=authenticate();

    validateJWTData($decoded);

    $authenticatedType=
        getAuthenticatedType($decoded);

    if($authenticatedType==='admin'){

        $adminAuth=authenticateAdmin();

        checkAdminRole(
            $adminAuth,
            ['admin']
        );

        $authenticatedId=
            getAuthenticatedId($adminAuth);

        if($authenticatedId<=0){
            sendResponse(
                false,
                'Invalid authenticated admin.',
                null,
                401
            );
        }

        $accessType='admin';

    }elseif($authenticatedType==='user'){

        $authenticatedId=
            getAuthenticatedId($decoded);

        if($authenticatedId<=0){
            sendResponse(
                false,
                'Invalid authenticated user.',
                null,
                401
            );
        }

        $accessType='user';

    }else{

        sendResponse(
            false,
            'Invalid authenticated account type.',
            null,
            403
        );
    }
}

/*
|--------------------------------------------------------------------------
| ALLOWED QUERY PARAMETERS
|--------------------------------------------------------------------------
*/

$allowedParams=[
    'id',
    'instagram_link_id'
];

foreach(array_keys($_GET) as $param){

    if(!in_array($param,$allowedParams,true)){
        sendResponse(
            false,
            "Invalid query parameter: {$param}.",
            [
                'allowed_parameters'=>$allowedParams
            ],
            422
        );
    }
}

foreach($_GET as $key=>$value){

    if(is_array($value)){
        sendResponse(
            false,
            "{$key} must be a single value.",
            null,
            422
        );
    }
}

/*
|--------------------------------------------------------------------------
| ID HELPER
|--------------------------------------------------------------------------
*/

function instagramViewPositiveInt(
    mixed $value,
    string $field
):?int{

    if($value===null||$value===''){
        return null;
    }

    if(
        is_array($value)||
        is_object($value)||
        is_bool($value)
    ){
        sendResponse(
            false,
            "{$field} must be a valid positive integer.",
            null,
            422
        );
    }

    $value=trim((string)$value);

    if(
        !preg_match(
            '/^[1-9][0-9]*$/',
            $value
        )
    ){
        sendResponse(
            false,
            "{$field} must be a valid positive integer.",
            null,
            422
        );
    }

    $max=(string)PHP_INT_MAX;

    if(
        strlen($value)>strlen($max)||
        (
            strlen($value)===strlen($max)&&
            strcmp($value,$max)>0
        )
    ){
        sendResponse(
            false,
            "{$field} exceeds allowed integer limit.",
            null,
            422
        );
    }

    return (int)$value;
}

/*
|--------------------------------------------------------------------------
| ID VALIDATION
|--------------------------------------------------------------------------
*/

$id=instagramViewPositiveInt(
    $_GET['id']??null,
    'id'
);

$instagramLinkId=instagramViewPositiveInt(
    $_GET['instagram_link_id']??null,
    'instagram_link_id'
);

if(
    $id!==null&&
    $instagramLinkId!==null&&
    $id!==$instagramLinkId
){
    sendResponse(
        false,
        'id and instagram_link_id cannot contain different values.',
        null,
        422
    );
}

$selectedId=
    $instagramLinkId??$id;

if($selectedId===null){
    sendResponse(
        false,
        'id or instagram_link_id is required.',
        [
            'required_parameter'=>'id'
        ],
        422
    );
}

try{

    /*
    |--------------------------------------------------------------------------
    | ACCESS CONDITION
    |--------------------------------------------------------------------------
    */

    $statusCondition=
        $accessType==='admin'
            ?''
            :" AND pil.status='active'";

    /*
    |--------------------------------------------------------------------------
    | FETCH INSTAGRAM LINK
    |--------------------------------------------------------------------------
    */

    $stmt=$pdo->prepare(
        "SELECT
            pil.id AS instagram_link_id,
            pil.variant_id,
            pil.instagram_url,
            pil.link_title,
            pil.is_primary,
            pil.status AS instagram_link_status,
            pil.created_by,
            pil.created_at AS instagram_link_created_at,
            pil.updated_at AS instagram_link_updated_at,

            a.name AS created_by_admin_name,
            a.email AS created_by_admin_email,
            a.mobile AS created_by_admin_mobile,
            a.status AS created_by_admin_status,

            pv.id AS product_variant_id,
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
            pv.created_at AS variant_created_at,
            pv.updated_at AS variant_updated_at,

            p.name AS product_name,
            p.slug AS product_slug,
            p.description AS product_description,
            p.status AS product_status,

            s.name AS size_name,
            s.status AS size_status,

            c.name AS color_name,
            c.hex_code AS color_hex_code,
            c.status AS color_status

         FROM product_variant_instagram_links pil

         INNER JOIN product_variants pv
            ON pv.id=pil.variant_id

         LEFT JOIN products p
            ON p.id=pv.product_id

         LEFT JOIN sizes s
            ON s.id=pv.size_id

         LEFT JOIN colors c
            ON c.id=pv.color_id

         LEFT JOIN admins a
            ON a.id=pil.created_by

         WHERE pil.id=:id
         {$statusCondition}

         LIMIT 1"
    );

    $stmt->bindValue(
        ':id',
        $selectedId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $row=$stmt->fetch(
        PDO::FETCH_ASSOC
    );

    /*
    |--------------------------------------------------------------------------
    | NOT FOUND / INACTIVE PUBLIC
    |--------------------------------------------------------------------------
    */

    if(!$row){

        if($accessType!=='admin'){

            $existsStmt=$pdo->prepare(
                "SELECT
                    id,
                    status
                 FROM product_variant_instagram_links
                 WHERE id=:id
                 LIMIT 1"
            );

            $existsStmt->bindValue(
                ':id',
                $selectedId,
                PDO::PARAM_INT
            );

            $existsStmt->execute();

            $existing=
                $existsStmt->fetch(
                    PDO::FETCH_ASSOC
                );

            if(
                $existing&&
                $existing['status']==='inactive'
            ){
                sendResponse(
                    false,
                    'This Instagram link is not available.',
                    null,
                    404
                );
            }
        }

        sendResponse(
            false,
            'Instagram link not found.',
            [
                'id'=>$selectedId
            ],
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VARIANT ID
    |--------------------------------------------------------------------------
    */

    $variantId=
        (int)$row['product_variant_id'];

    /*
    |--------------------------------------------------------------------------
    | VARIANT IMAGES
    |--------------------------------------------------------------------------
    |
    | Admin -> all images
    | User/Public -> active images only
    |
    */

    $imageStatusCondition=
        $accessType==='admin'
            ?''
            :" AND status='active'";

    $imageStmt=$pdo->prepare(
        "SELECT
            id,
            variant_id,
            image,
            alt_text,
            is_primary,
            sort_order,
            status,
            created_at,
            updated_at
         FROM product_variant_images
         WHERE variant_id=:variant_id
         {$imageStatusCondition}
         ORDER BY
            is_primary DESC,
            sort_order ASC,
            id ASC"
    );

    $imageStmt->bindValue(
        ':variant_id',
        $variantId,
        PDO::PARAM_INT
    );

    $imageStmt->execute();

    $imageRows=
        $imageStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    $images=[];

    foreach($imageRows as $image){

        $images[]=[
            'id'=>
                (int)$image['id'],

            'variant_id'=>
                (int)$image['variant_id'],

            'image'=>
                $image['image'],

            'alt_text'=>
                $image['alt_text'],

            'is_primary'=>
                (int)$image['is_primary'],

            'sort_order'=>
                (int)$image['sort_order'],

            'status'=>
                $image['status'],

            'created_at'=>
                $image['created_at'],

            'updated_at'=>
                $image['updated_at']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PRIMARY IMAGE
    |--------------------------------------------------------------------------
    */

    $primaryImage=null;

    foreach($images as $image){

        if($image['is_primary']===1){
            $primaryImage=$image;
            break;
        }
    }

    if(
        $primaryImage===null&&
        !empty($images)
    ){
        $primaryImage=$images[0];
    }

    /*
    |--------------------------------------------------------------------------
    | STOCK
    |--------------------------------------------------------------------------
    */

    $stockQuantity=
        (int)$row['stock_quantity'];

    $reservedQuantity=
        (int)$row['reserved_quantity'];

    $availableQuantity=max(
        0,
        $stockQuantity-$reservedQuantity
    );

    $lowStockLimit=
        (int)$row['low_stock_limit'];

    if($availableQuantity<=0){

        $stockStatus=
            'out_of_stock';

    }elseif(
        $availableQuantity<=$lowStockLimit
    ){

        $stockStatus=
            'low_stock';

    }else{

        $stockStatus=
            'in_stock';
    }

    /*
    |--------------------------------------------------------------------------
    | PRICING / DISCOUNT
    |--------------------------------------------------------------------------
    */

    $originalPrice=
        (float)$row['original_price'];

    $gstAmount=
        (float)$row['gst_amount'];

    $priceWithTax=
        (float)$row['price_with_tax'];

    $sellingPrice=
        (float)$row['selling_price'];

    $calculatedDiscountAmount=max(
        0,
        round(
            $priceWithTax-$sellingPrice,
            2
        )
    );

    $calculatedDiscountPercentage=
        0.00;

    if(
        $priceWithTax>0&&
        $calculatedDiscountAmount>0
    ){
        $calculatedDiscountPercentage=
            round(
                (
                    $calculatedDiscountAmount/
                    $priceWithTax
                )*100,
                2
            );
    }

    /*
    |--------------------------------------------------------------------------
    | INSTAGRAM LINK
    |--------------------------------------------------------------------------
    */

    $instagramLink=[
        'id'=>
            (int)$row[
                'instagram_link_id'
            ],

        'variant_id'=>
            $variantId,

        'instagram_url'=>
            $row[
                'instagram_url'
            ],

        'link_title'=>
            $row[
                'link_title'
            ],

        'is_primary'=>
            (int)$row[
                'is_primary'
            ],

        'status'=>
            $row[
                'instagram_link_status'
            ],

        'created_at'=>
            $row[
                'instagram_link_created_at'
            ],

        'updated_at'=>
            $row[
                'instagram_link_updated_at'
            ]
    ];

    /*
    |--------------------------------------------------------------------------
    | ADMIN CREATOR DETAILS
    |--------------------------------------------------------------------------
    */

    if($accessType==='admin'){

        $instagramLink['created_by']=
            $row['created_by']!==null
                ?(int)$row['created_by']
                :null;

        $instagramLink['created_by_admin']=
            $row['created_by']!==null
                ?[
                    'id'=>
                        (int)$row[
                            'created_by'
                        ],

                    'name'=>
                        $row[
                            'created_by_admin_name'
                        ],

                    'email'=>
                        $row[
                            'created_by_admin_email'
                        ],

                    'mobile'=>
                        $row[
                            'created_by_admin_mobile'
                        ],

                    'status'=>
                        $row[
                            'created_by_admin_status'
                        ]
                ]
                :null;
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'Instagram link retrieved successfully.',
        [
            'instagram_link'=>
                $instagramLink,

            'product'=>[
                'id'=>
                    $row['product_id']!==null
                        ?(int)$row['product_id']
                        :null,

                'name'=>
                    $row['product_name'],

                'slug'=>
                    $row['product_slug'],

                'description'=>
                    $row['product_description'],

                'status'=>
                    $row['product_status']
            ],

            'product_variant'=>[
                'id'=>
                    $variantId,

                'product_id'=>
                    $row['product_id']!==null
                        ?(int)$row['product_id']
                        :null,

                'sku'=>
                    $row['sku'],

                'variant_name'=>
                    $row['variant_name'],

                'size'=>
                    $row['size_id']!==null
                        ?[
                            'id'=>
                                (int)$row['size_id'],

                            'name'=>
                                $row['size_name'],

                            'status'=>
                                $row['size_status']
                        ]
                        :null,

                'color'=>
                    $row['color_id']!==null
                        ?[
                            'id'=>
                                (int)$row['color_id'],

                            'name'=>
                                $row['color_name'],

                            'hex_code'=>
                                $row['color_hex_code'],

                            'status'=>
                                $row['color_status']
                        ]
                        :null,

                'pricing'=>[
                    'original_price'=>
                        number_format(
                            $originalPrice,
                            2,
                            '.',
                            ''
                        ),

                    'gst_rate'=>
                        $row['gst_rate'],

                    'gst_amount'=>
                        number_format(
                            $gstAmount,
                            2,
                            '.',
                            ''
                        ),

                    'price_with_tax'=>
                        number_format(
                            $priceWithTax,
                            2,
                            '.',
                            ''
                        ),

                    'discount_type'=>
                        $row[
                            'discount_type'
                        ],

                    'discount_value'=>
                        $row[
                            'discount_value'
                        ],

                    'calculated_discount_amount'=>
                        number_format(
                            $calculatedDiscountAmount,
                            2,
                            '.',
                            ''
                        ),

                    'calculated_discount_percentage'=>
                        number_format(
                            $calculatedDiscountPercentage,
                            2,
                            '.',
                            ''
                        ),

                    'selling_price'=>
                        number_format(
                            $sellingPrice,
                            2,
                            '.',
                            ''
                        )
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
                    (int)$row[
                        'is_available'
                    ],

                'primary_image'=>
                    $primaryImage,

                'images'=>
                    $images,

                'created_at'=>
                    $row[
                        'variant_created_at'
                    ],

                'updated_at'=>
                    $row[
                        'variant_updated_at'
                    ]
            ],

            'access'=>[
                'authenticated'=>
                    $accessType!=='public',

                'type'=>
                    $accessType,

                'authenticated_id'=>
                    $authenticatedId,

                'scope'=>
                    $accessType==='admin'
                        ?'all_instagram_links'
                        :'active_instagram_links_only'
            ]
        ],
        200
    );

}catch(PDOException $e){

    sendResponse(
        false,
        'Unable to retrieve Instagram link.',
        defined('APP_ENV')&&
        APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );

}catch(Throwable $e){

    sendResponse(
        false,
        'An unexpected error occurred while retrieving Instagram link.',
        defined('APP_ENV')&&
        APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );
}