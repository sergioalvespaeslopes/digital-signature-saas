<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Signature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Fpdf\Fpdf;

class DocumentController extends Controller
{
    public function index() {
        $documents = auth()->user()->documents;
        return view('documents.index', compact('documents'));
    }

    public function create() {
        return view('documents.create');
    }

    public function store(Request $request) {
        $request->validate([
            'document' => 'required|mimes:pdf|max:2048',
            'title' => 'required|string|max:255',
        ]);

        $filePath = $request->file('document')->store('documents', 'public');

        $document = Document::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'path' => $filePath,
            'is_signed' => false,
        ]);

        // Adicionando signatários
        foreach ($request->signatories as $signatoryId) {
            Signature::create([
                'document_id' => $document->id,
                'user_id' => $signatoryId,
                'is_signed' => false,
            ]);
        }

        return redirect()->route('documents.index')->with('success', 'Document uploaded and signatories added.');
    }

    public function signDocument($id) {
        $signature = Signature::where('document_id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Marcar assinatura como concluída
        $signature->update(['is_signed' => true]);

        // Verificar se todos assinaram
        $document = $signature->document;
        if ($document->signatures()->where('is_signed', false)->count() == 0) {
            $document->update(['is_signed' => true]);
        }

        // Gerar PDF com assinatura usando FPDF
        $pdf = new Fpdf();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(40, 10, 'Signed by ' . auth()->user()->name);
        $pdfPath = 'signed_documents/' . $document->id . '.pdf';
        Storage::put($pdfPath, $pdf->Output('S'));

        return redirect()->route('documents.index')->with('success', 'You have signed the document.');
    }
}