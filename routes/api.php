<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Sales\CashierController;
use App\Http\Controllers\Sales\SalesmanController;
use App\Http\Controllers\Sales\DiscountController;
use App\Http\Controllers\RolePermissionController;

use App\Http\Controllers\Master\BookController;
use App\Http\Controllers\Master\AuthorController;
use App\Http\Controllers\Master\ProductController;
use App\Http\Controllers\Master\MagazineController;
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
use App\Http\Controllers\Master\SubCategoryL2Controller;

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
use App\Http\Controllers\Payment\CodManagementController;


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
Route::group(['prefix' => 'price-levels'], function () {
    Route::get('/', [PriceLevelController::class, 'index']);
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
    Route::group(['prefix' => 'locations', 'middleware' => ['can:view location']], function () {
        Route::get('/generate-code', [LocationController::class, 'generateLocationCode']);
        Route::get('/', [LocationController::class, 'index']);
        Route::get('/{loca_code}', [LocationController::class, 'show']);
        Route::post('/', [LocationController::class, 'store'])->middleware('can:create location');
        Route::put('/{loca_code}', [LocationController::class, 'update'])->middleware('can:edit location');
    });

    // book type routes
    Route::group(['prefix' => 'book-types', 'middleware' => ['can:view book-type']], function () {
        Route::get('/generate-code', [BookTypeController::class, 'generateBookTypeCode']);
        Route::get('/', [BookTypeController::class, 'index']);
        Route::get('/{bkt_code}', [BookTypeController::class, 'show']);
        Route::post('/', [BookTypeController::class, 'store'])->middleware('can:create book-type');
        Route::put('/{bkt_code}', [BookTypeController::class, 'update'])->middleware('can:edit book-type');
    });

    // department routes
    Route::group(['prefix' => 'departments', 'middleware' => ['can:view department']], function () {
        Route::get('/generate-code', [DepartmentController::class, 'generateDepartmentCode']);
        Route::get('/', [DepartmentController::class, 'index']);
        Route::get('/{dep_code}', [DepartmentController::class, 'show']);
        Route::post('/', [DepartmentController::class, 'store'])->middleware('can:create department');
        Route::put('/{dep_code}', [DepartmentController::class, 'update'])->middleware('can:edit department');
        Route::get('/{dep_code}/categories', [DepartmentController::class, 'categories']);
    });

    // sub category routes
    Route::group(['prefix' => 'sub-categories'], function () {
        Route::get('/generate-code', [SubCategoryController::class, 'generateSubCategoryCode']);
        Route::get('/search', [SubCategoryController::class, 'search']);
        Route::get('/', [SubCategoryController::class, 'index']);
        Route::get('/{scat_code}', [SubCategoryController::class, 'show']);
        Route::post('/', [SubCategoryController::class, 'store']);
        Route::put('/{scat_code}', [SubCategoryController::class, 'update']);
    });

    // sub category level 2 routes
    Route::group(['prefix' => 'sub-categories-l2'], function () {
        Route::get('/generate-code', [SubCategoryL2Controller::class, 'generateSubCategoryL2Code']);
        Route::get('/search', [SubCategoryL2Controller::class, 'search']);
        Route::get('/', [SubCategoryL2Controller::class, 'index']);
        Route::get('/{scat_l2_code}', [SubCategoryL2Controller::class, 'show']);
        Route::post('/', [SubCategoryL2Controller::class, 'store']);
        Route::put('/{scat_l2_code}', [SubCategoryL2Controller::class, 'update']);
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
        Route::get('/invoice', [PaymentTypeController::class, 'paymentTypesInvoice']);
        Route::get('/load-all-setoff-payments/{customer_code}/{location}', [PaymentTypeController::class, 'loadAllSetoffPayments']);
    });

    // publisher routes
    Route::group(['prefix' => 'publishers', 'middleware' => ['can:view publisher']], function () {
        Route::get('/generate-code', [PublisherController::class, 'generatePublisherCode']);
        Route::post('/import', [PublisherController::class, 'import'])->middleware('can:import publisher');
        Route::get('/export', [PublisherController::class, 'export'])->middleware('can:export publisher');
        Route::get('/', [PublisherController::class, 'index']);
        Route::get('/search', [PublisherController::class, 'search']);
        Route::get('/{pub_code}', [PublisherController::class, 'show']);
        Route::post('/', [PublisherController::class, 'store'])->middleware('can:create publisher');
        Route::put('/{pub_code}', [PublisherController::class, 'update'])->middleware('can:edit publisher');
    });

    // supplier routes
    Route::group(['prefix' => 'suppliers', 'middleware' => ['can:view supplier']], function () {
        Route::get('/generate-code', [SupplierController::class, 'generateSupplierCode']);
        Route::post('/import', [SupplierController::class, 'import'])->middleware('can:import supplier');
        Route::get('/export', [SupplierController::class, 'export'])->middleware('can:export supplier');
        Route::get('/', [SupplierController::class, 'index']);
        Route::get('/search', [SupplierController::class, 'search']);
        Route::get('/{sup_code}', [SupplierController::class, 'show']);
        Route::post('/', [SupplierController::class, 'store'])->middleware('can:create supplier');
        Route::put('/{sup_code}', [SupplierController::class, 'update'])->middleware('can:edit supplier');
        Route::post('/status/{sup_code}/{status}', [SupplierController::class, 'changeSupplierStatus'])->middleware('can:edit supplier');
    });

    // author routes
    Route::group(['prefix' => 'authors', 'middleware' => ['can:view author']], function () {
        Route::get('/generate-code', [AuthorController::class, 'generateAuthorCode']);
        Route::post('/import', [AuthorController::class, 'import'])->middleware('can:import author');
        Route::get('/export', [AuthorController::class, 'export'])->middleware('can:export author');
        Route::get('/', [AuthorController::class, 'index']);
        Route::get('/search', [AuthorController::class, 'search']);
        Route::get('/{auth_code}', [AuthorController::class, 'show']);
        Route::post('/', [AuthorController::class, 'store'])->middleware('can:create author');
        Route::put('/{auth_code}', [AuthorController::class, 'update'])->middleware('can:edit author');
    });

    // customer routes
    Route::group(['prefix' => 'customers', 'middleware' => ['can:view customer']], function () {
        Route::get('/generate-code', [CustomerController::class, 'generateCustomerCode']);
        Route::get('/', [CustomerController::class, 'index']);
        Route::get('/search', [CustomerController::class, 'search']);
        Route::get('/{customer_code}', [CustomerController::class, 'show']);
        Route::post('/', [CustomerController::class, 'store'])->middleware('can:create customer');
        Route::put('/{customer_code}', [CustomerController::class, 'update'])->middleware('can:edit customer');
    });

    // books routes
    Route::group(['prefix' => 'books', 'middleware' => ['can:view book']], function () {
        Route::get('/generate-code', [BookController::class, 'generateBookCode']);
        Route::get('/{prod_code}', [BookController::class, 'show']);
        Route::post('/import', [BookController::class, 'import'])->middleware('can:import book');
        Route::get('/export', [BookController::class, 'export'])->middleware('can:export book');
        Route::get('/', [BookController::class, 'index']);
        Route::post('/', [BookController::class, 'store'])->middleware('can:create book');
        Route::put('/{prod_code}', [BookController::class, 'update'])->middleware('can:edit book');
    });

    // magazines routes
    Route::group(['prefix' => 'magazines', 'middleware' => ['can:view magazine']], function () {
        Route::get('/generate-code', [MagazineController::class, 'generateMagazineCode']);
        Route::get('/{prod_code}', [MagazineController::class, 'show']);
        Route::get('/', [MagazineController::class, 'index']);
        Route::post('/', [MagazineController::class, 'store'])->middleware('can:create magazine');
        Route::put('/{prod_code}', [MagazineController::class, 'update'])->middleware('can:edit magazine');
    });

    // products routes
    Route::group(['prefix' => 'products', 'middleware' => ['can:view product']], function () {
        Route::get('/{prod_code}/check-open-stock', [ProductController::class, 'checkOpenStock']);
        Route::get('/{prod_code}/stock-by-location', [ProductController::class, 'stockByLocation']);
        Route::get('/{prod_code}/bin-card/export', [ProductController::class, 'exportBinCard'])->middleware('can:export bin-card');
        Route::get('/generate-code', [ProductController::class, 'generateProductCode']);
        Route::get('/{prod_code}/bin-card', [ProductController::class, 'binCard']);
        Route::get('/open-stocks', [ProductController::class, 'getOpenStocks']);
        Route::get('/basic-search', [ProductController::class, 'searchBasic']);
        Route::get('/unit-types', [ProductController::class, 'unitTypes']);
        Route::get('/search', [ProductController::class, 'search']);
        Route::get('/{prod_code}', [ProductController::class, 'show']);
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store'])->middleware('can:create product');
        Route::post('/store-open-stock', [ProductController::class, 'storeOpenStock'])->middleware('can:create product');
        Route::put('/{prod_code}', [ProductController::class, 'update'])->middleware('can:edit product');
    });

    // product discounts routes
    Route::group(['prefix' => 'products/discounts'], function () {
        Route::post('/filter', [DiscountController::class, 'filter']);
        Route::post('/update', [DiscountController::class, 'update']);
        Route::get('/list', [DiscountController::class, 'list']);
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
        Route::put('/{id}', [PriceLevelController::class, 'update']);
        Route::delete('/delete-all', [PriceLevelController::class, 'deleteAll']);
        Route::delete('/{id}', [PriceLevelController::class, 'destroy']);
    });

    // common transactions routes
    Route::group(['prefix' => 'transactions'], function () {
        Route::get('/load-transaction-by-code/{doc_number}/{status}/{iid}', [TransactionController::class, 'loadTransactionByCode']);
        Route::get('/load-vat-transaction-by-code/{doc_number}/{status}/{iid}', [TransactionController::class, 'loadVatTransactionByCode']);
        Route::get('/generate-code/{type}/{loca_code}', [TransactionController::class, 'getTempTransactionNumber']);
        Route::get('/load-all-transactions', [TransactionController::class, 'loadAllTransactions']);
        Route::get('/load-all-advances', [TransactionController::class, 'loadAllAdvances']);
        Route::get('/load-advance-by-code/{doc_number}', [TransactionController::class, 'loadAdvanceByCode']);
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
    Route::group(['prefix' => 'item-requests', 'middleware' => ['can:view item-request']], function () {
        Route::get('/load-item-request-by-code/{doc_number}/{status}/{iid}', [ItemRequestController::class, 'loadItemRequestByCode']);
        Route::get('/load-applied-by-status', [ItemRequestController::class, 'loadAppliedItemRequestsByStatus']);
        Route::get('/load-all-item-requests', [ItemRequestController::class, 'loadAllItemRequests']);
        Route::get('/unsaved-sessions', [ItemRequestController::class, 'getUnsavedSessions']);
        Route::get('/history/{doc_no}', [ItemRequestController::class, 'getItemReqHistory']);

        Route::put('/update-item-req-product/{id}', [ItemRequestController::class, 'updateItemReqProduct'])->middleware('can:create item-request');

        Route::post('/save-ir', [ItemRequestController::class, 'store'])->middleware('can:post item-request');
        Route::post('/reject-ir', [ItemRequestController::class, 'rejectIr'])->middleware('can:cancel item-request');
        Route::post('/store-item-req', [ItemRequestController::class, 'storeItemReq'])->middleware('can:create item-request');
        Route::post('/cancel-updates/{doc_no}', [ItemRequestController::class, 'cancelItemReqUpdates'])->middleware('can:cancel item-request');

        Route::delete('/delete-item-req-detail/{doc_no}/{line_no}', [ItemRequestController::class, 'deleteItemReqDetail'])->middleware('can:create item-request');
    });

    // purchase order routes
    Route::group(['prefix' => 'purchase-orders', 'middleware' => ['can:view purchase-order']], function () {
        Route::get('/unsaved-sessions', [PurchaseOrderController::class, 'getUnsavedSessions']);
        Route::post('/save-po', [PurchaseOrderController::class, 'store'])->middleware('can:post purchase-order');
    });

    // good receive note routes
    Route::group(['prefix' => 'good-receive-notes', 'middleware' => ['can:view good-receive-note']], function () {
        Route::get('/unsaved-sessions', [GoodReceiveNoteController::class, 'getUnsavedSessions']);
        Route::post('/save-grn', [GoodReceiveNoteController::class, 'store'])->middleware('can:post good-receive-note');
    });

    // supplier return note routes
    Route::group(['prefix' => 'supplier-return-notes', 'middleware' => ['can:view supplier-return-note']], function () {
        Route::get('/unsaved-sessions', [SupplierReturnNoteController::class, 'getUnsavedSessions']);
        Route::post('/save-srn', [SupplierReturnNoteController::class, 'store'])->middleware('can:post supplier-return-note');
    });

    // transfer good note routes
    Route::group(['prefix' => 'transfer-good-notes', 'middleware' => ['can:view transfer-good-note']], function () {
        Route::get('/unsaved-sessions', [TransferGoodNoteController::class, 'getUnsavedSessions']);
        Route::get('/applied', [TransferGoodNoteController::class, 'getAppliedTransactions']);

        Route::put('/update-product/{id}', [TransferGoodNoteController::class, 'updateProduct'])->middleware('can:create transfer-good-note');

        Route::post('/add-product', [TransferGoodNoteController::class, 'addProduct'])->middleware('can:create transfer-good-note');
        Route::post('/save-tgn', [TransferGoodNoteController::class, 'store'])->middleware('can:post transfer-good-note');
    });

    // accept good note routes
    Route::group(['prefix' => 'accept-good-notes', 'middleware' => ['can:view accept-good-note']], function () {
        Route::get('/load-agn-by-code/{doc_number}/{status}/{iid}', [AcceptGoodNoteController::class, 'loadAgnByCode'])->middleware('can:view accept-good-note');
        Route::get('/load-all-agn', [AcceptGoodNoteController::class, 'loadAllAgns'])->middleware('can:view accept-good-note');

        Route::put('/update-product/{id}', [AcceptGoodNoteController::class, 'updateProduct'])->middleware('can:edit accept-good-note');

        Route::post('/draft-agn', [AcceptGoodNoteController::class, 'draftAgn'])->middleware('can:edit accept-good-note');
        Route::post('/save-agn', [AcceptGoodNoteController::class, 'store'])->middleware('can:edit accept-good-note');
    });

    // transfer good return routes
    Route::group(['prefix' => 'transfer-good-returns'], function () {
        Route::post('/save-tgr', [TransferGoodReturnController::class, 'store']);
    });

    // stock adjustments routes
    Route::group(['prefix' => 'stock-adjustments', 'middleware' => ['can:view stock-adjustment']], function () {
        Route::get('/unsaved-sessions', [StockAdjustmentController::class, 'getUnsavedSessions']);
        Route::get('/stock', [StockAdjustmentController::class, 'getProductStock']);

        Route::put('/update-product/{id}', [StockAdjustmentController::class, 'updateProduct'])->middleware('can:create stock-adjustment');
        Route::put('/draft/{doc_no}', [StockAdjustmentController::class, 'updateDraft'])->middleware('can:create stock-adjustment');

        Route::post('/add-product', [StockAdjustmentController::class, 'addProduct'])->middleware('can:create stock-adjustment');
        Route::post('/draft', [StockAdjustmentController::class, 'draft'])->middleware('can:create stock-adjustment');
        Route::post('/save-sta', [StockAdjustmentController::class, 'store'])->middleware('can:post stock-adjustment');
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
        Route::get('/load-all-pmt', [PaymentVoucherController::class, 'loadAllPaymentVouchers']);
        Route::get('/load-payment-by-code/{doc_number}', [PaymentVoucherController::class, 'loadPaymentByCode']);
    });

    // customer receipt routes
    Route::group(['prefix' => 'customer-receipts'], function () {
        Route::get('/pending-receipts/{customer_code}/{loca_code}/{iid}', [PaymentVoucherController::class, 'getPendingCustomerReceipts']);
        Route::get('/available-set-offs/{customer_code}/{loca_code}', [PaymentVoucherController::class, 'getAvailableSetOffsRec']);

        Route::post('/save-rec', [PaymentVoucherController::class, 'receiptStore']);
        Route::post('/generate-code', [PaymentVoucherController::class, 'getRecNumber']);
        Route::get('/load-all-rec', [PaymentVoucherController::class, 'loadAllCustomerReceipts']);
        Route::get('/load-receipt-by-code/{doc_number}', [PaymentVoucherController::class, 'loadPaymentByCode']);
    });

    // cod management routes
    Route::group(['prefix' => 'cod-management'], function () {
        Route::get('/', [CodManagementController::class, 'index']);
        Route::put('/{id}/received', [CodManagementController::class, 'markAsReceived']);
    });

    // invoice routes
    Route::group(['prefix' => 'invoices', 'middleware' => ['can:view invoice']], function () {
        Route::get('/load-invoice-by-code/{doc_number}/{status}/{iid}', [InvoiceController::class, 'loadInvoiceByCode']);
        Route::get('/company-header', [InvoiceController::class, 'getCompanyHeader']);
        Route::get('/load-vat-invoice-by-code/{doc_number}/{status}/{iid}', [InvoiceController::class, 'loadVatInvoiceByCode']);
        Route::get('/temp-products/{doc_no}', [InvoiceController::class, 'getTempProducts']);
        Route::get('/unsaved-sessions', [InvoiceController::class, 'getUnsavedSessions']);
        Route::get('/load-all-invoices', [InvoiceController::class, 'loadAllInvoices']);
        Route::get('/applied', [InvoiceController::class, 'getAppliedInv']);

        Route::put('/draft-inv/{doc_no}', [InvoiceController::class, 'updateInvoice'])->middleware('can:create invoice');
        Route::put('/update-product/{id}', [InvoiceController::class, 'updateProduct'])->middleware('can:create invoice');

        Route::post('/unsave/{doc_no}', [InvoiceController::class, 'removeUnsaved'])->middleware('can:create invoice');
        Route::post('/add-product', [InvoiceController::class, 'addProduct'])->middleware('can:create invoice');
        Route::post('/draft-inv', [InvoiceController::class, 'draftInvoice'])->middleware('can:create invoice');
        Route::post('/save-inv', [InvoiceController::class, 'store'])->middleware('can:create invoice');

        Route::delete('/delete-detail/{doc_no}/{line_no}', [InvoiceController::class, 'deleteTempDetail'])->middleware('can:create invoice');
    });

    // Pos sales & Day end routes
    Route::group(['prefix' => 'Sales'], function () {
        Route::get('/pos-sales-summary', [SalesController::class, 'getPosSalesSummary'])->middleware('can:process day-end');
        Route::post('/process-day-end', [SalesController::class, 'processDayEnd'])->middleware('can:process day-end');
    });

    // Report routes
    Route::group(['prefix' => 'reports'], function () {
        Route::get('/current-stock-report', [ReportController::class, 'getCurrentStockReport'])->middleware('can:view current-stock-report');
        Route::get('/current-stock-report/export', [ReportController::class, 'exportCurrentStockReport'])->middleware('can:view current-stock-report');
        Route::get('/pos-sales-summary-report', [ReportController::class, 'getPosSalesSummaryReport'])->middleware('can:view pos-sales-summary-report');
        Route::get('/pos-collection-summary-report', [ReportController::class, 'getPosCollectionSummaryReport'])->middleware('can:view daily-collection-report');
        Route::get('/sales-report', [ReportController::class, 'getSalesReport'])->middleware('can:view sales-report');
        Route::get('/sales-report/export', [ReportController::class, 'exportSalesReport'])->middleware('can:view sales-report');
    });

    // Dashboard routes
    Route::group(['prefix' => 'dashboard'], function () {
        Route::get('/stats', [DashboardController::class, 'getStats'])->middleware('can:view dashboard stats');
        Route::get('/bills', [DashboardController::class, 'getBills'])->middleware('can:view dashboard stats');
        Route::post('/calculate-charges', [DashboardController::class, 'calculateCharges']);
    });
});


// Role and User Management
Route::middleware(['auth:sanctum', 'role:super-admin|admin'])
    ->prefix('v1')
    ->group(function () {
        // Roles
        Route::get('/roles', [RolePermissionController::class, 'getRoles'])->middleware('can:view role');
        Route::post('/roles', [RolePermissionController::class, 'createRole'])->middleware('can:create role');
        Route::delete('/roles/{id}', [RolePermissionController::class, 'deleteRole'])->middleware('can:delete role');
        Route::put('/roles/{id}', [RolePermissionController::class, 'updateRole'])->middleware('can:edit role');

        // Permissions
        Route::get('/permissions', [RolePermissionController::class, 'getPermissions'])->middleware('permission:view permission|permission assign');
        Route::post('/permissions', [RolePermissionController::class, 'createPermission'])->middleware('can:create permission');
        Route::put('/permissions/{id}', [RolePermissionController::class, 'updatePermission'])->middleware('can:edit permission');
        Route::delete('/permissions/{id}', [RolePermissionController::class, 'deletePermission'])->middleware('can:delete permission');

        // Role-Permission assignment
        Route::get('/roles/{roleId}/permissions', [RolePermissionController::class, 'getRolePermissions'])->middleware('can:permission assign');
        Route::post('/roles/{roleId}/permissions', [RolePermissionController::class, 'assignPermissionsToRole'])->middleware('can:permission assign');

        // User-Permission assignment
        Route::get('/users/{userId}/permissions', [RolePermissionController::class, 'getUserPermissions'])->middleware('can:permission assign');
        Route::post('/users/{userId}/permissions', [RolePermissionController::class, 'assignPermissionsToUser'])->middleware('can:permission assign');

        // User-Role assignment
        Route::post('/users/{userId}/roles', [RolePermissionController::class, 'assignRolesToUser'])->middleware('can:permission assign');

        // User management routes
        Route::group(['prefix' => 'users'], function () {
            Route::get('/', [UserController::class, 'index'])->middleware('can:view user');
            Route::post('/', [UserController::class, 'store'])->middleware('can:create user');
            Route::get('/{id}', [UserController::class, 'show'])->middleware('can:view user');
            Route::put('/{id}', [UserController::class, 'update'])->middleware('can:edit user');
            Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('can:delete user');
        });
    });



// Route::group(['prefix' => 'v'], function () {
//     Route::group(['middleware' => ['auth:sanctum'], 'prefix' => 'master'], function () {});

//     Route::group(['middleware' => ['auth:sanctum'], 'prefix' => 'transaction'], function () {});

//     Route::group(['prefix' => 'department'], function () {});
// });


// http://localhost:8000/api/v1/master/authors...
// http://localhost:8000/api/v1/transaction/grn...
