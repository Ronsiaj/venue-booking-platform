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
| HELPERS
|--------------------------------------------------------------------------
*/

function instagramPositiveInt(
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
| ALLOWED QUERY PARAMETERS
|--------------------------------------------------------------------------
*/

$allowedParams=[
    'q',
    'id',
    'instagram_link_id',
    'variant_id',
    'product_variant_id',
    'product_id',
    'size_id',
    'color_id',
    'status',
    'is_primary',
    'page',
    'limit',
    'sort_by',
    'sort_order'
];

foreach(array_keys($_GET) as $param){

    if(
        !in_array(
            $param,
            $allowedParams,
            true
        )
    ){
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
| SEARCH
|--------------------------------------------------------------------------
*/

$q=trim(
    (string)($_GET['q']??'')
);

if(mb_strlen($q)>200){
    sendResponse(
        false,
        'q must not exceed 200 characters.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| INSTAGRAM LINK ID
|--------------------------------------------------------------------------
*/

$id=instagramPositiveInt(
    $_GET['id']??null,
    'id'
);

$instagramLinkId=instagramPositiveInt(
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

$selectedInstagramLinkId=
    $instagramLinkId??$id;

/*
|--------------------------------------------------------------------------
| VARIANT ID
|--------------------------------------------------------------------------
*/

$variantId=instagramPositiveInt(
    $_GET['variant_id']??null,
    'variant_id'
);

$productVariantId=instagramPositiveInt(
    $_GET['product_variant_id']??null,
    'product_variant_id'
);

if(
    $variantId!==null&&
    $productVariantId!==null&&
    $variantId!==$productVariantId
){
    sendResponse(
        false,
        'variant_id and product_variant_id cannot contain different values.',
        null,
        422
    );
}

$selectedVariantId=
    $productVariantId??$variantId;

/*
|--------------------------------------------------------------------------
| OTHER IDS
|--------------------------------------------------------------------------
*/

$productId=instagramPositiveInt(
    $_GET['product_id']??null,
    'product_id'
);

$sizeId=instagramPositiveInt(
    $_GET['size_id']??null,
    'size_id'
);

$colorId=instagramPositiveInt(
    $_GET['color_id']??null,
    'color_id'
);

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$status=strtolower(
    trim(
        (string)($_GET['status']??'')
    )
);

if(
    $status!==''&&
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

/*
|--------------------------------------------------------------------------
| STATUS ACCESS VALIDATION
|--------------------------------------------------------------------------
*/

if(
    $accessType!=='admin'&&
    $status==='inactive'
){
    sendResponse(
        false,
        'Inactive Instagram links are not available for this access type.',
        null,
        403
    );
}

/*
|--------------------------------------------------------------------------
| IS PRIMARY
|--------------------------------------------------------------------------
*/

$isPrimary=null;

if(
    array_key_exists(
        'is_primary',
        $_GET
    )&&
    $_GET['is_primary']!==''
){

    $isPrimaryInput=trim(
        (string)$_GET['is_primary']
    );

    if(
        !in_array(
            $isPrimaryInput,
            [
                '0',
                '1'
            ],
            true
        )
    ){
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

    $isPrimary=
        (int)$isPrimaryInput;
}

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$pageInput=trim(
    (string)($_GET['page']??'1')
);

$limitInput=trim(
    (string)($_GET['limit']??'20')
);

if(
    !preg_match(
        '/^[1-9][0-9]*$/',
        $pageInput
    )
){
    sendResponse(
        false,
        'page must be a valid positive integer.',
        null,
        422
    );
}

if(
    !preg_match(
        '/^[1-9][0-9]*$/',
        $limitInput
    )
){
    sendResponse(
        false,
        'limit must be a valid positive integer.',
        null,
        422
    );
}

$page=(int)$pageInput;
$limit=(int)$limitInput;

if($limit>100){
    sendResponse(
        false,
        'limit must be between 1 and 100.',
        null,
        422
    );
}

$offset=
    ($page-1)*$limit;

/*
|--------------------------------------------------------------------------
| SORTING
|--------------------------------------------------------------------------
*/

$sortColumns=[
    'id'=>'pil.id',
    'variant_id'=>'pil.variant_id',
    'product_variant_id'=>'pil.variant_id',
    'product_id'=>'pv.product_id',
    'link_title'=>'pil.link_title',
    'is_primary'=>'pil.is_primary',
    'status'=>'pil.status',
    'created_at'=>'pil.created_at',
    'updated_at'=>'pil.updated_at',
    'sku'=>'pv.sku',
    'variant_name'=>'pv.variant_name',
    'original_price'=>'pv.original_price',
    'selling_price'=>'pv.selling_price'
];

$sortBy=trim(
    (string)(
        $_GET['sort_by']
        ??'created_at'
    )
);

$sortOrder=strtolower(
    trim(
        (string)(
            $_GET['sort_order']
            ??'desc'
        )
    )
);

if(
    !array_key_exists(
        $sortBy,
        $sortColumns
    )
){
    sendResponse(
        false,
        'Invalid sort_by.',
        [
            'allowed_values'=>
                array_keys($sortColumns)
        ],
        422
    );
}

if(
    !in_array(
        $sortOrder,
        [
            'asc',
            'desc'
        ],
        true
    )
){
    sendResponse(
        false,
        'sort_order must be asc or desc.',
        null,
        422
    );
}

$sqlSortColumn=
    $sortColumns[$sortBy];

$sqlSortOrder=
    strtoupper($sortOrder);

try{

    /*
    |--------------------------------------------------------------------------
    | WHERE
    |--------------------------------------------------------------------------
    */

    $where=[];
    $params=[];

    /*
    |--------------------------------------------------------------------------
    | ACCESS SCOPE
    |--------------------------------------------------------------------------
    */

    if($accessType!=='admin'){
        $where[]=
            "pil.status='active'";
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS FILTER
    |--------------------------------------------------------------------------
    */

    if(
        $accessType==='admin'&&
        $status!==''
    ){
        $where[]=
            'pil.status=:status';

        $params[':status']=
            $status;
    }

    /*
    |--------------------------------------------------------------------------
    | INSTAGRAM LINK ID
    |--------------------------------------------------------------------------
    */

    if(
        $selectedInstagramLinkId!==null
    ){
        $where[]=
            'pil.id=:instagram_link_id';

        $params[':instagram_link_id']=
            $selectedInstagramLinkId;
    }

    /*
    |--------------------------------------------------------------------------
    | VARIANT
    |--------------------------------------------------------------------------
    */

    if($selectedVariantId!==null){

        $where[]=
            'pil.variant_id=:variant_id';

        $params[':variant_id']=
            $selectedVariantId;
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT
    |--------------------------------------------------------------------------
    */

    if($productId!==null){

        $where[]=
            'pv.product_id=:product_id';

        $params[':product_id']=
            $productId;
    }

    /*
    |--------------------------------------------------------------------------
    | SIZE
    |--------------------------------------------------------------------------
    */

    if($sizeId!==null){

        $where[]=
            'pv.size_id=:size_id';

        $params[':size_id']=
            $sizeId;
    }

    /*
    |--------------------------------------------------------------------------
    | COLOR
    |--------------------------------------------------------------------------
    */

    if($colorId!==null){

        $where[]=
            'pv.color_id=:color_id';

        $params[':color_id']=
            $colorId;
    }

    /*
    |--------------------------------------------------------------------------
    | PRIMARY
    |--------------------------------------------------------------------------
    */

    if($isPrimary!==null){

        $where[]=
            'pil.is_primary=:is_primary';

        $params[':is_primary']=
            $isPrimary;
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    if($q!==''){

        $where[]="
            CONCAT_WS(
                ' ',
                pil.id,
                pil.variant_id,
                pil.instagram_url,
                pil.link_title,
                pil.is_primary,
                pil.status,
                pv.id,
                pv.product_id,
                pv.sku,
                pv.variant_name,
                pv.original_price,
                pv.selling_price,
                p.name,
                p.slug,
                s.name,
                c.name,
                c.hex_code,
                a.name,
                a.email
            ) LIKE :search_q
        ";

        $params[':search_q']=
            '%'.$q.'%';
    }

    $whereSql=
        $where
            ?' WHERE '.implode(
                ' AND ',
                $where
            )
            :'';

    /*
    |--------------------------------------------------------------------------
    | BASE TABLES
    |--------------------------------------------------------------------------
    */

    $baseFrom="
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
    ";

    /*
    |--------------------------------------------------------------------------
    | TOTAL COUNT
    |--------------------------------------------------------------------------
    */

    $countStmt=$pdo->prepare(
        "SELECT COUNT(*)
         {$baseFrom}
         {$whereSql}"
    );

    foreach($params as $key=>$value){

        $countStmt->bindValue(
            $key,
            $value,
            is_int($value)
                ?PDO::PARAM_INT
                :PDO::PARAM_STR
        );
    }

    $countStmt->execute();

    $totalRecords=
        (int)$countStmt->fetchColumn();

    $totalPages=
        $totalRecords>0
            ?(int)ceil(
                $totalRecords/$limit
            )
            :0;

    if(
        $totalPages>0&&
        $page>$totalPages
    ){
        sendResponse(
            false,
            'Requested page exceeds total available pages.',
            [
                'requested_page'=>$page,
                'total_pages'=>$totalPages
            ],
            422
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    $summaryStmt=$pdo->prepare(
        "SELECT
            COUNT(*) AS total_records,

            COALESCE(
                SUM(
                    CASE
                        WHEN pil.status='active'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS active_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN pil.status='inactive'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS inactive_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN pil.is_primary=1
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS primary_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN pil.is_primary=0
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS non_primary_count,

            COUNT(
                DISTINCT pil.variant_id
            ) AS variant_count,

            COUNT(
                DISTINCT pv.product_id
            ) AS product_count

         {$baseFrom}
         {$whereSql}"
    );

    foreach($params as $key=>$value){

        $summaryStmt->bindValue(
            $key,
            $value,
            is_int($value)
                ?PDO::PARAM_INT
                :PDO::PARAM_STR
        );
    }

    $summaryStmt->execute();

    $summary=
        $summaryStmt->fetch(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | MAIN LIST
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

         {$baseFrom}

         {$whereSql}

         ORDER BY
            {$sqlSortColumn} {$sqlSortOrder},
            pil.id DESC

         LIMIT :limit
         OFFSET :offset"
    );

    foreach($params as $key=>$value){

        $stmt->bindValue(
            $key,
            $value,
            is_int($value)
                ?PDO::PARAM_INT
                :PDO::PARAM_STR
        );
    }

    $stmt->bindValue(
        ':limit',
        $limit,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':offset',
        $offset,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $rows=
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | VARIANT IDS
    |--------------------------------------------------------------------------
    */

    $variantIds=[];

    foreach($rows as $row){

        $currentVariantId=
            (int)$row['product_variant_id'];

        if($currentVariantId>0){
            $variantIds[
                $currentVariantId
            ]=true;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VARIANT IMAGES
    |--------------------------------------------------------------------------
    */

    $imageMap=[];

    if($variantIds){

        $variantIds=
            array_keys($variantIds);

        $placeholders=[];

        foreach(
            $variantIds as
            $index=>$currentVariantId
        ){
            $placeholders[]=
                ':image_variant_id_'.$index;
        }

        $imageInSql=
            implode(
                ',',
                $placeholders
            );

        /*
        |--------------------------------------------------------------------------
        | IMAGE ACCESS
        |--------------------------------------------------------------------------
        |
        | Admin -> all images
        | User/Public -> active images only
        |
        */

        $imageStatusSql=
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

             WHERE variant_id IN ({$imageInSql})

             {$imageStatusSql}

             ORDER BY
                variant_id ASC,
                is_primary DESC,
                sort_order ASC,
                id ASC"
        );

        foreach(
            $variantIds as
            $index=>$currentVariantId
        ){
            $imageStmt->bindValue(
                ':image_variant_id_'.$index,
                $currentVariantId,
                PDO::PARAM_INT
            );
        }

        $imageStmt->execute();

        $imageRows=
            $imageStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        foreach($imageRows as $image){

            $currentVariantId=
                (int)$image['variant_id'];

            $imageMap[
                $currentVariantId
            ][]=[
                'id'=>
                    (int)$image['id'],

                'variant_id'=>
                    $currentVariantId,

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
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT RESULT
    |--------------------------------------------------------------------------
    */

    $instagramLinks=[];

    foreach($rows as $row){

        $currentVariantId=
            (int)$row['product_variant_id'];

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
        | PRICING
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
                $priceWithTax-
                $sellingPrice,
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
        | IMAGES
        |--------------------------------------------------------------------------
        */

        $images=
            $imageMap[
                $currentVariantId
            ]??[];

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
            $primaryImage=
                $images[0];
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
                $currentVariantId,

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

            $instagramLink[
                'created_by'
            ]=
                $row['created_by']!==null
                    ?(int)$row['created_by']
                    :null;

            $instagramLink[
                'created_by_admin'
            ]=
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
        | FINAL ITEM
        |--------------------------------------------------------------------------
        */

        $instagramLinks[]=[
            'instagram_link'=>
                $instagramLink,

            'product'=>[
                'id'=>
                    $row['product_id']!==null
                        ?(int)$row['product_id']
                        :null,

                'name'=>
                    $row[
                        'product_name'
                    ],

                'slug'=>
                    $row[
                        'product_slug'
                    ],

                'description'=>
                    $row[
                        'product_description'
                    ],

                'status'=>
                    $row[
                        'product_status'
                    ]
            ],

            'product_variant'=>[
                'id'=>
                    $currentVariantId,

                'product_id'=>
                    $row['product_id']!==null
                        ?(int)$row['product_id']
                        :null,

                'sku'=>
                    $row['sku'],

                'variant_name'=>
                    $row[
                        'variant_name'
                    ],

                'size'=>
                    $row['size_id']!==null
                        ?[
                            'id'=>
                                (int)$row[
                                    'size_id'
                                ],

                            'name'=>
                                $row[
                                    'size_name'
                                ],

                            'status'=>
                                $row[
                                    'size_status'
                                ]
                        ]
                        :null,

                'color'=>
                    $row['color_id']!==null
                        ?[
                            'id'=>
                                (int)$row[
                                    'color_id'
                                ],

                            'name'=>
                                $row[
                                    'color_name'
                                ],

                            'hex_code'=>
                                $row[
                                    'color_hex_code'
                                ],

                            'status'=>
                                $row[
                                    'color_status'
                                ]
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
                        $row[
                            'gst_rate'
                        ],

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
            ]
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'Instagram links retrieved successfully.',
        [
            'instagram_links'=>
                $instagramLinks,

            'summary'=>[
                'total_records'=>
                    (int)(
                        $summary[
                            'total_records'
                        ]??0
                    ),

                'active_count'=>
                    (int)(
                        $summary[
                            'active_count'
                        ]??0
                    ),

                'inactive_count'=>
                    (int)(
                        $summary[
                            'inactive_count'
                        ]??0
                    ),

                'primary_count'=>
                    (int)(
                        $summary[
                            'primary_count'
                        ]??0
                    ),

                'non_primary_count'=>
                    (int)(
                        $summary[
                            'non_primary_count'
                        ]??0
                    ),

                'variant_count'=>
                    (int)(
                        $summary[
                            'variant_count'
                        ]??0
                    ),

                'product_count'=>
                    (int)(
                        $summary[
                            'product_count'
                        ]??0
                    )
            ],

            'pagination'=>[
                'page'=>$page,

                'limit'=>$limit,

                'total_records'=>
                    $totalRecords,

                'total_pages'=>
                    $totalPages,

                'has_previous'=>
                    $page>1,

                'has_next'=>
                    $page<$totalPages
            ],

            'filters'=>[
                'q'=>
                    $q!==''?$q:null,

                'instagram_link_id'=>
                    $selectedInstagramLinkId,

                'variant_id'=>
                    $selectedVariantId,

                'product_id'=>
                    $productId,

                'size_id'=>
                    $sizeId,

                'color_id'=>
                    $colorId,

                'status'=>
                    $accessType==='admin'
                        ?(
                            $status!==''
                                ?$status
                                :null
                        )
                        :'active',

                'is_primary'=>
                    $isPrimary
            ],

            'sorting'=>[
                'sort_by'=>$sortBy,
                'sort_order'=>$sortOrder
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
        'Unable to retrieve Instagram links.',
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
        'An unexpected error occurred while retrieving Instagram links.',
        defined('APP_ENV')&&
        APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );
}