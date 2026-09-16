<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        // Ambil data setting pertama. Jika tabel masih kosong, buat otomatis data default.
        $setting = Setting::firstOrCreate(
            ['id' => 1], 
            [
                'store_name' => 'Toko Sembako Bpk. Condro',
                'store_address' => 'Jl. Raya Desa No. 1, Malang',
                'store_phone' => '0812-3456-7890',
                'receipt_note' => 'Barang yang sudah dibeli tidak dapat ditukar.'
            ]
        );

        return Inertia::render('Settings/Index', [
            'setting' => $setting
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'store_name' => 'required|string|max:255',
            'store_address' => 'nullable|string',
            'store_phone' => 'nullable|string|max:50',
            'receipt_note' => 'nullable|string|max:255',
        ]);

        $setting = Setting::first();
        $setting->update($request->all());

        return redirect()->back()->with('success', 'Pengaturan Toko berhasil disimpan!');
    }
}