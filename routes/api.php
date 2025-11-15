<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\UserController;

use App\Http\Controllers\Master\BookController;
use App\Http\Controllers\Master\AuthorController;
use App\Http\Controllers\Master\ProductController;
use App\Http\Controllers\Master\BookTypeController;
use App\Http\Controllers\Master\CategoryController;
use App\Http\Controllers\Master\LocationController;
use App\Http\Controllers\Master\LanguageController;
use App\Http\Controllers\Master\SupplierController;
use App\Http\Controllers\Master\PublisherController;
use App\Http\Controllers\Master\DepartmentController;
use App\Http\Controllers\Master\SubCategoryController;

use App\Http\Controllers\Transaction\PurchaseOrderController;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', function (Request $request) {
        return $request->user();
    });
});


// Middleware doesn't work here
// Route::group(['prefix' => 'v1'], function () {
//     #author routes
//     Route::group(['prefix' => 'authors'], function () {
//         Route::get('/', [AuthorController::class, 'index']);
//     });
// });

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::get('/me', [AuthController::class, 'me']);

    // location routes
    Route::group(['prefix' => 'locations'], function () {
        Route::get('/generate-code', [LocationController::class, 'generateLocationCode']);
        Route::get('/', [LocationController::class, 'index']);
        Route::get('/{loca_code}', [LocationController::class, 'show']);
        Route::post('/', [LocationController::class, 'store']);
        Route::put('/{loca_code}', [LocationController::class, 'update']);
    });

    // book type routes
    Route::group(['prefix' => 'book-types'], function () {
        Route::get('/generate-code', [BookTypeController::class, 'generateBookTypeCode']);
        Route::get('/', [BookTypeController::class, 'index']);
        Route::get('/{bkt_code}', [BookTypeController::class, 'show']);
        Route::post('/', [BookTypeController::class, 'store']);
        Route::put('/{bkt_code}', [BookTypeController::class, 'update']);
    });

    // department routes
    Route::group(['prefix' => 'departments'], function () {
        Route::get('/generate-code', [DepartmentController::class, 'generateDepartmentCode']);
        Route::get('/', [DepartmentController::class, 'index']);
        Route::get('/{dep_code}', [DepartmentController::class, 'show']);
        Route::post('/', [DepartmentController::class, 'store']);
        Route::put('/{dep_code}', [DepartmentController::class, 'update']);
        Route::get('/{dep_code}/categories', [DepartmentController::class, 'categories']);
    });

    // sub category routes
    Route::group(['prefix' => 'sub-categories'], function () {
        Route::get('/generate-code', [SubCategoryController::class, 'generateSubCategoryCode']);
        Route::get('/', [SubCategoryController::class, 'index']);
        Route::get('/{scat_code}', [SubCategoryController::class, 'show']);
        Route::post('/', [SubCategoryController::class, 'store']);
        Route::put('/{scat_code}', [SubCategoryController::class, 'update']);
    });

    // category routes
    Route::group(['prefix' => 'categories'], function () {
        Route::get('/generate-code', [CategoryController::class, 'generateCategoryCode']);
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{cat_code}', [CategoryController::class, 'show']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{cat_code}', [CategoryController::class, 'update']);
        Route::get('/{cat_code}/sub-categories', [CategoryController::class, 'subCategories']);
    });

    // language routes
    Route::group(['prefix' => 'languages'], function () {
        Route::get('/', [LanguageController::class, 'index']);
    });

    // publisher routes
    Route::group(['prefix' => 'publishers'], function () {
        Route::get('/generate-code', [PublisherController::class, 'generatePublisherCode']);
        Route::get('/', [PublisherController::class, 'index']);
        Route::get('/search', [PublisherController::class, 'search']);
        Route::get('/{pub_code}', [PublisherController::class, 'show']);
        Route::post('/', [PublisherController::class, 'store']);
        Route::put('/{pub_code}', [PublisherController::class, 'update']);
    });

    // supplier routes
    Route::group(['prefix' => 'suppliers'], function () {
        Route::get('/generate-code', [SupplierController::class, 'generateSupplierCode']);
        Route::get('/', [SupplierController::class, 'index']);
        Route::get('/search', [SupplierController::class, 'search']);
        Route::get('/{sup_code}', [SupplierController::class, 'show']);
        Route::post('/', [SupplierController::class, 'store']);
        Route::put('/{sup_code}', [SupplierController::class, 'update']);
        Route::post('/status/{sup_code}/{status}', [SupplierController::class, 'changeSupplierStatus']);
    });

    // author routes
    Route::group(['prefix' => 'authors'], function () {
        Route::get('/generate-code', [AuthorController::class, 'generateAuthorCode']);
        Route::get('/', [AuthorController::class, 'index']);
        Route::get('/search', [AuthorController::class, 'search']);
        Route::get('/{auth_code}', [AuthorController::class, 'show']);
        Route::post('/', [AuthorController::class, 'store']);
        Route::put('/{auth_code}', [AuthorController::class, 'update']);
    });

    // books routes
    Route::group(['prefix' => 'books'], function () {
        Route::get('/generate-code', [BookController::class, 'generateBookCode']);
        Route::get('/', [BookController::class, 'index']);
        Route::get('/{prod_code}', [BookController::class, 'show']);
        Route::post('/', [BookController::class, 'store']);
        Route::put('/{prod_code}', [BookController::class, 'update']);
    });

    // products routes
    Route::group(['prefix' => 'products'], function () {
        Route::get('/generate-code', [ProductController::class, 'generateProductCode']);
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/search', [ProductController::class, 'search']);
        Route::get('/unit-types', [ProductController::class, 'unitTypes']);
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/{prod_code}', [ProductController::class, 'show']);
        Route::put('/{prod_code}', [ProductController::class, 'update']);
    });

    // purchase order routes
    Route::group(['prefix' => 'purchase-orders'], function () {
        Route::get('/load-purchase-order-by-code/{doc_number}/{status}/{iid}', [PurchaseOrderController::class, 'loadPurchaseOrderByCode']);
        Route::get('/view-purchase-order-by-code/{doc_number}/{status}/{iid}', [PurchaseOrderController::class, 'viewPurchaseOrderByCode']);
        Route::get('/load-all-purchase-orders', [PurchaseOrderController::class, 'loadAllPurchaseOrders']);
        Route::get('/generate-code/{loca_code}', [PurchaseOrderController::class, 'getTempPoNumber']);
        Route::get('/temp-products/{doc_no}', [PurchaseOrderController::class, 'getTempProducts']);
        Route::get('/unsaved-sessions', [PurchaseOrderController::class, 'getUnsavedSessions']);
        Route::put('/update-product/{id}', [PurchaseOrderController::class, 'updateProduct']);
        Route::post('/unsave/{doc_no}', [PurchaseOrderController::class, 'removeUnsaved']);
        Route::post('/draft', [PurchaseOrderController::class, 'draftPurchaseOrder']);
        Route::post('/add-product', [PurchaseOrderController::class, 'addProduct']);
        Route::post('/save-po', [PurchaseOrderController::class, 'store']);
        Route::put('/draft/{doc_no}', [PurchaseOrderController::class, 'updateDraftPurchaseOrder']);
        Route::delete('/delete-detail/{doc_no}/{line_no}', [PurchaseOrderController::class, 'deleteTempDetail']);
    });

    // Role and Permission routes
    // Route::post('/role', [RolePermissionController::class, 'createRole']);
    // Route::post('/permission', [RolePermissionController::class, 'createPermission']);
    // Route::post('/role/permission', [RolePermissionController::class, 'assignPermissionToRole']);
    // Route::post('/user/role', [RolePermissionController::class, 'assignRoleToUser']);
});

//1st
// Route::middleware(['auth:sanctum', 'role:admin', 'prefix' => 'v1'])->group(function () {
//     // Roles
//     Route::get('/roles', [RolePermissionController::class, 'getRoles']);
//     Route::post('/roles', [RolePermissionController::class, 'createRole']);
//     Route::delete('/roles/{id}', [RolePermissionController::class, 'deleteRole']);

//     // Permissions
//     Route::get('/permissions', [RolePermissionController::class, 'getPermissions']);
//     Route::post('/permissions', [RolePermissionController::class, 'createPermission']);
//     Route::delete('/permissions/{id}', [RolePermissionController::class, 'deletePermission']);
// });

//2nd
Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('v1')
    ->group(function () {
        // Roles
        Route::get('/roles', [RolePermissionController::class, 'getRoles']);
        Route::post('/roles', [RolePermissionController::class, 'createRole']);
        Route::delete('/roles/{id}', [RolePermissionController::class, 'deleteRole']);
        Route::put('/roles/{id}', [RolePermissionController::class, 'updateRole']);

        // Permissions
        Route::get('/permissions', [RolePermissionController::class, 'getPermissions']);
        Route::post('/permissions', [RolePermissionController::class, 'createPermission']);
        Route::delete('/permissions/{id}', [RolePermissionController::class, 'deletePermission']);

        // Role-Permission assignment
        Route::get('/roles/{roleId}/permissions', [RolePermissionController::class, 'getRolePermissions']);
        Route::post('/roles/{roleId}/permissions', [RolePermissionController::class, 'assignPermissionsToRole']);

        // User-Permission assignment
        Route::get('/users/{userId}/permissions', [RolePermissionController::class, 'getUserPermissions']);
        Route::post('/users/{userId}/permissions', [RolePermissionController::class, 'assignPermissionsToUser']);

        // User-Role assignment
        Route::post('/users/{userId}/roles', [RolePermissionController::class, 'assignRolesToUser']);

        // User management routes (admin only)
        Route::group(['prefix' => 'users'], function () {
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::get('/{id}', [UserController::class, 'show']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
        });
    });


// Route::group(['prefix' => 'v'], function () {
//     Route::group(['middleware' => ['auth:sanctum'], 'prefix' => 'master'], function () {});

//     Route::group(['middleware' => ['auth:sanctum'], 'prefix' => 'transaction'], function () {});

//     Route::group(['prefix' => 'department'], function () {});
// });


// http://localhost:8000/api/v1/master/authors...
// http://localhost:8000/api/v1/transaction/grn...
