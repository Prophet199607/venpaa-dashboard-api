<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ClientBarcodeSetting;

class ClientBarcodeSettingController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => ClientBarcodeSetting::all()
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ip_address' => 'required|string|unique:client_barcode_settings',
            'template_path' => 'nullable|string',
            'output_dir' => 'nullable|string',
            'data_file_name' => 'nullable|string',
            'is_active' => 'nullable|boolean'
        ]);

        $setting = ClientBarcodeSetting::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Settings saved successfully',
            'data' => $setting
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'success' => true,
            'data' => ClientBarcodeSetting::findOrFail($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $setting = ClientBarcodeSetting::findOrFail($id);
        
        $data = $request->validate([
            'ip_address' => 'string|unique:client_barcode_settings,ip_address,' . $id,
            'template_path' => 'nullable|string',
            'output_dir' => 'nullable|string',
            'data_file_name' => 'nullable|string',
            'is_active' => 'nullable|boolean'
        ]);

        $setting->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $setting
        ]);
    }

    public function destroy($id)
    {
        $setting = ClientBarcodeSetting::findOrFail($id);
        $setting->delete();

        return response()->json([
            'success' => true,
            'message' => 'Settings deleted successfully'
        ]);
    }
}
