<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Sales\CashierController;
use App\Http\Controllers\Sales\SalesmanController;
use App\Http\Controllers\RolePermissionController;

use App\Http\Controllers\Master\BookController;
use App\Http\Controllers\Master\AuthorController;
use App\Http\Controllers\Master\ProductController;
use App\Http\Controllers\Master\CustomerController;
use App\Http\Controllers\Master\BookTypeController;
use App\Http\Controllers\Master\CategoryController;
use App\Http\Controllers\Master\LocationController;
use App\Http\Controllers\Master\LanguageController;
use App\Http\Controllers\Master\SupplierController;
use App\Http\Controllers\Master\PublisherController;
use App\Http\Controllers\Master\PriceLevelController;
use App\Http\Controllers\Master\DepartmentController;
use App\Http\Controllers\Master\SubCategoryController;
use App\Http\Controllers\Master\PaymentTypeController;
use App\Http\Controllers\Master\BarcodePrintController;
use App\Http\Controllers\Master\ClientBarcodeSettingController;

use App\Http\Controllers\Transaction\InvoiceController;
use App\Http\Controllers\Transaction\TransactionController;
use App\Http\Controllers\Transaction\ItemRequestController;
use App\Http\Controllers\Transaction\PurchaseOrderController;
use App\Http\Controllers\Transaction\ProductDiscardController;
use App\Http\Controllers\Transaction\AcceptGoodNoteController;
use App\Http\Controllers\Transaction\GoodReceiveNoteController;
use App\Http\Controllers\Transaction\StockAdjustmentController;
use App\Http\Controllers\Transaction\TransferGoodNoteController;
use App\Http\Controllers\Transaction\SupplierReturnNoteController;
use App\Http\Controllers\Transaction\TransferGoodReturnController;

use App\Http\Controllers\Payment\PaymentVoucherController;


/*

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
Route::get('/locations', [LocationController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', function (Request $request) {
        return $request->user();
    });
});

// POS Sales routes
Route::group(['prefix' => 'Sales'], function () {
    Route::post('/InsertPosSales', [SalesController::class, 'InsertPosSales']);
});

Route::group(['prefix' => 'products'], function () {
    Route::get('/basic-search', [ProductController::class, 'searchBasic']);
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

    // payment type routes
    Route::group(['prefix' => 'payment-types'], function () {
        Route::get('/', [PaymentTypeController::class, 'index']);
    });

    // publisher routes
    Route::group(['prefix' => 'publishers'], function () {
        Route::get('/generate-code', [PublisherController::class, 'generatePublisherCode']);
        Route::post('/import', [PublisherController::class, 'import']);
        Route::get('/export', [PublisherController::class, 'export']);
        Route::get('/', [PublisherController::class, 'index']);
        Route::get('/search', [PublisherController::class, 'search']);
        Route::get('/{pub_code}', [PublisherController::class, 'show']);
        Route::post('/', [PublisherController::class, 'store']);
        Route::put('/{pub_code}', [PublisherController::class, 'update']);
    });

    // supplier routes
    Route::group(['prefix' => 'suppliers'], function () {
        Route::get('/generate-code', [SupplierController::class, 'generateSupplierCode']);
        Route::post('/import', [SupplierController::class, 'import']);
        Route::get('/export', [SupplierController::class, 'export']);
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
        Route::post('/import', [AuthorController::class, 'import']);
        Route::get('/export', [AuthorController::class, 'export']);
        Route::get('/', [AuthorController::class, 'index']);
        Route::get('/search', [AuthorController::class, 'search']);
        Route::get('/{auth_code}', [AuthorController::class, 'show']);
        Route::post('/', [AuthorController::class, 'store']);
        Route::put('/{auth_code}', [AuthorController::class, 'update']);
    });

    // customer routes
    Route::group(['prefix' => 'customers'], function () {
        Route::get('/generate-code', [CustomerController::class, 'generateCustomerCode']);
        Route::get('/', [CustomerController::class, 'index']);
        Route::get('/search', [CustomerController::class, 'search']);
        Route::get('/{customer_code}', [CustomerController::class, 'show']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::put('/{customer_code}', [CustomerController::class, 'update']);
    });

    // books routes
    Route::group(['prefix' => 'books'], function () {
        Route::get('/generate-code', [BookController::class, 'generateBookCode']);
        Route::post('/import', [BookController::class, 'import']);
        Route::get('/export', [BookController::class, 'export']);
        Route::get('/', [BookController::class, 'index']);
        Route::get('/{prod_code}', [BookController::class, 'show']);
        Route::post('/', [BookController::class, 'store']);
        Route::put('/{prod_code}', [BookController::class, 'update']);
    });

    // products routes
    Route::group(['prefix' => 'products'], function () {
        Route::get('/generate-code', [ProductController::class, 'generateProductCode']);
        Route::get('/basic-search', [ProductController::class, 'searchBasic']);
        Route::get('/unit-types', [ProductController::class, 'unitTypes']);
        Route::get('/search', [ProductController::class, 'search']);
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/{prod_code}', [ProductController::class, 'show']);
        Route::put('/{prod_code}', [ProductController::class, 'update']);
    });

    // salesman routes
    Route::group(['prefix' => 'salesmen'], function () {
        Route::get('/generate-code', [SalesmanController::class, 'generateSalesmanCode']);
        Route::get('/', [SalesmanController::class, 'index']);
        Route::get('/{sales_code}', [SalesmanController::class, 'show']);
        Route::post('/', [SalesmanController::class, 'store']);
        Route::put('/{id}', [SalesmanController::class, 'update']);
    });

    // cashier routes
    Route::group(['prefix' => 'cashiers'], function () {
        Route::get('/generate-code', [CashierController::class, 'generateCashierCode']);
        Route::get('/form-data', [CashierController::class, 'getFormData']);
        Route::get('/', [CashierController::class, 'index']);
        Route::get('/{id}', [CashierController::class, 'show']);
        Route::post('/', [CashierController::class, 'store']);
        Route::put('/{id}', [CashierController::class, 'update']);
    });

    // price level routes
    Route::group(['prefix' => 'price-levels'], function () {
        Route::get('/', [PriceLevelController::class, 'index']);
        Route::post('/', [PriceLevelController::class, 'store']);
        Route::post('/batch', [PriceLevelController::class, 'batchStore']);
        Route::delete('/expired', [PriceLevelController::class, 'deleteExpired']);
        Route::delete('/product/{prod_code}', [PriceLevelController::class, 'deleteByProduct']);
        Route::delete('/{id}', [PriceLevelController::class, 'destroy']);
    });

    // barcode print routes
    Route::group(['prefix' => 'barcodes'], function () {
        Route::post('/print', [BarcodePrintController::class, 'print']);
        Route::apiResource('/settings', ClientBarcodeSettingController::class);
    });

    // common transactions routes
    Route::group(['prefix' => 'transactions'], function () {
        Route::get('/load-transaction-by-code/{doc_number}/{status}/{iid}', [TransactionController::class, 'loadTransactionByCode']);
        Route::get('/generate-code/{type}/{loca_code}', [TransactionController::class, 'getTempTransactionNumber']);
        Route::get('/load-all-transactions', [TransactionController::class, 'loadAllTransactions']);
        Route::get('/temp-products/{doc_no}', [TransactionController::class, 'getTempProducts']);
        Route::get('/applied', [TransactionController::class, 'getAppliedTransactions']);

        Route::put('/update-product/{id}', [TransactionController::class, 'updateProduct']);
        Route::put('/draft/{doc_no}', [TransactionController::class, 'updateTransaction']);

        Route::post('/add-product', [TransactionController::class, 'addProduct']);
        Route::post('/draft', [TransactionController::class, 'draftTransaction']);
        Route::post('/save-advance', [TransactionController::class, 'AdvanceStore']);
        Route::post('/unsave/{doc_no}', [TransactionController::class, 'removeUnsaved']);

        Route::delete('/delete-detail/{doc_no}/{line_no}', [TransactionController::class, 'deleteTempDetail']);
    });

    // item request routes
    Route::group(['prefix' => 'item-requests'], function () {
        Route::get('/load-item-request-by-code/{doc_number}/{status}/{iid}', [ItemRequestController::class, 'loadItemRequestByCode']);
        Route::get('/load-applied-by-status', [ItemRequestController::class, 'loadAppliedItemRequestsByStatus']);
        Route::get('/load-all-item-requests', [ItemRequestController::class, 'loadAllItemRequests']);
        Route::get('/unsaved-sessions', [ItemRequestController::class, 'getUnsavedSessions']);
        Route::get('/history/{doc_no}', [ItemRequestController::class, 'getItemReqHistory']);

        Route::put('/update-item-req-product/{id}', [ItemRequestController::class, 'updateItemReqProduct']);

        Route::post('/save-ir', [ItemRequestController::class, 'store']);
        Route::post('/reject-ir', [ItemRequestController::class, 'rejectIr']);
        Route::post('/store-item-req', [ItemRequestController::class, 'storeItemReq']);
        Route::post('/cancel-updates/{doc_no}', [ItemRequestController::class, 'cancelItemReqUpdates']);

        Route::delete('/delete-item-req-detail/{doc_no}/{line_no}', [ItemRequestController::class, 'deleteItemReqDetail']);
    });

    // purchase order routes
    Route::group(['prefix' => 'purchase-orders'], function () {
        Route::get('/unsaved-sessions', [PurchaseOrderController::class, 'getUnsavedSessions']);
        Route::post('/save-po', [PurchaseOrderController::class, 'store']);
    });

    // good receive note routes
    Route::group(['prefix' => 'good-receive-notes'], function () {
        Route::get('/unsaved-sessions', [GoodReceiveNoteController::class, 'getUnsavedSessions']);
        Route::post('/save-grn', [GoodReceiveNoteController::class, 'store']);
    });

    // supplier return note routes
    Route::group(['prefix' => 'supplier-return-notes'], function () {
        Route::get('/unsaved-sessions', [SupplierReturnNoteController::class, 'getUnsavedSessions']);
        Route::post('/save-srn', [SupplierReturnNoteController::class, 'store']);
    });

    // transfer good note routes
    Route::group(['prefix' => 'transfer-good-notes'], function () {
        Route::get('/unsaved-sessions', [TransferGoodNoteController::class, 'getUnsavedSessions']);
        Route::get('/applied', [TransferGoodNoteController::class, 'getAppliedTransactions']);

        Route::put('/update-product/{id}', [TransferGoodNoteController::class, 'updateProduct']);

        Route::post('/add-product', [TransferGoodNoteController::class, 'addProduct']);
        Route::post('/save-tgn', [TransferGoodNoteController::class, 'store']);
    });

    // accept good note routes
    Route::group(['prefix' => 'accept-good-notes'], function () {
        Route::get('/load-agn-by-code/{doc_number}/{status}/{iid}', [AcceptGoodNoteController::class, 'loadAgnByCode']);
        Route::get('/load-all-agn', [AcceptGoodNoteController::class, 'loadAllAgns']);

        Route::put('/update-product/{id}', [AcceptGoodNoteController::class, 'updateProduct']);

        Route::post('/draft-agn', [AcceptGoodNoteController::class, 'draftAgn']);
        Route::post('/save-agn', [AcceptGoodNoteController::class, 'store']);
    });

    // transfer good return routes
    Route::group(['prefix' => 'transfer-good-returns'], function () {
        Route::post('/save-tgr', [TransferGoodReturnController::class, 'store']);
    });

    // stock adjustments routes
    Route::group(['prefix' => 'stock-adjustments'], function () {
        Route::get('/unsaved-sessions', [StockAdjustmentController::class, 'getUnsavedSessions']);
        Route::get('/stock', [StockAdjustmentController::class, 'getProductStock']);

        Route::put('/update-product/{id}', [StockAdjustmentController::class, 'updateProduct']);

        Route::post('/add-product', [StockAdjustmentController::class, 'addProduct']);
        Route::post('/save-sta', [StockAdjustmentController::class, 'store']);
    });

    // product discards routes
    Route::group(['prefix' => 'product-discards'], function () {
        Route::get('/load-transaction-by-code/{doc_number}/{status}/{iid}', [ProductDiscardController::class, 'loadTransactionByCode']);
        Route::get('/load-all-transactions', [ProductDiscardController::class, 'loadAllTransactions']);
        Route::get('/unsaved-sessions', [ProductDiscardController::class, 'getUnsavedSessions']);
        Route::get('/discard-types', [ProductDiscardController::class, 'getDiscardTypes']);
        
        Route::put('/update-product/{id}', [ProductDiscardController::class, 'updateProduct']);

        Route::post('/add-product', [ProductDiscardController::class, 'addProduct']);
        Route::post('/save-pd', [ProductDiscardController::class, 'store']);
    });

    // payment voucher routes
    Route::group(['prefix' => 'payment-vouchers'], function () {
        Route::get('/pending-payments/{supplier_code}/{loca_code}/{iid}', [PaymentVoucherController::class, 'getPendingPaymentsVoucher']);
        Route::get('/available-set-offs/{supplier_code}/{loca_code}', [PaymentVoucherController::class, 'getAvailableSetOffs']);

        Route::post('/save-pmt', [PaymentVoucherController::class, 'store']);
        Route::post('/generate-code', [PaymentVoucherController::class, 'getPmtNumber']);
    });

    // invoice routes
    Route::group(['prefix' => 'invoices'], function () {
        Route::get('/load-invoice-by-code/{doc_number}/{status}/{iid}', [InvoiceController::class, 'loadInvoiceByCode']);
        Route::get('/temp-products/{doc_no}', [InvoiceController::class, 'getTempProducts']);
        Route::get('/unsaved-sessions', [InvoiceController::class, 'getUnsavedSessions']);
        Route::get('/load-all-invoices', [InvoiceController::class, 'loadAllInvoices']);
        Route::get('/applied', [InvoiceController::class, 'getAppliedInv']);

        Route::put('/draft-inv/{doc_no}', [InvoiceController::class, 'updateInvoice']);
        Route::put('/update-product/{id}', [InvoiceController::class, 'updateProduct']);

        Route::post('/unsave/{doc_no}', [InvoiceController::class, 'removeUnsaved']);
        Route::post('/add-product', [InvoiceController::class, 'addProduct']);
        Route::post('/draft-inv', [InvoiceController::class, 'draftInvoice']);
        Route::post('/save-inv', [InvoiceController::class, 'store']);

        Route::delete('/delete-detail/{doc_no}/{line_no}', [InvoiceController::class, 'deleteTempDetail']);
    });

    // Report routes
    Route::group(['prefix' => 'reports'], function () {
        Route::get('/stock-summary', [ReportController::class, 'getStockSummary']);
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
