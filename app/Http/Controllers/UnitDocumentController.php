<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\UnitDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UnitDocumentController extends Controller
{
    /** Dopuszczamy to, co realnie trafia do teczki lokalu: skany, zdjęcia i dokumenty biurowe. */
    private const ALLOWED = 'pdf,jpg,jpeg,png,webp,heic,doc,docx,odt,xls,xlsx,ods,txt';

    public function store(Request $request, Unit $unit)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:'.self::ALLOWED],
            'title' => ['nullable', 'string', 'max:255'],
        ], [], [
            'file' => 'plik',
            'title' => 'nazwa',
        ]);

        $file = $data['file'];

        $unit->documents()->create([
            'uploaded_by' => $request->user()?->id,
            'title' => ($data['title'] ?? null) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('unit-documents/'.$unit->id, 'local'),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('status', 'Plik został dodany do lokalu.');
    }

    public function download(UnitDocument $document)
    {
        abort_unless(Storage::disk($document->disk())->exists($document->path), 404);

        return Storage::disk($document->disk())->download($document->path, $document->original_name);
    }

    public function destroy(UnitDocument $document)
    {
        $document->delete();

        return back()->with('status', 'Plik został usunięty.');
    }
}
