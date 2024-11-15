<?php

namespace App\Controllers;

use App\Models\PdfModel;
use CodeIgniter\HTTP\ResponseInterface;
use Dompdf\Dompdf;

class PdfController extends BaseController
{
    private $session;
    private $pdfModel;

    public function __construct()
    {
        $this->session = service('session');
        $this->pdfModel = new PdfModel();
    }

    public function generate()
    {
        // Verifica se o usuário está autenticado
        if (!$this->session->has('user_id')) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Usuário não autenticado'])
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
        }

        // Recebe os dados para o PDF
        $data = $this->request->getJSON(true);

        // Cria o conteúdo HTML para o PDF
        $html = view('pdf_template', ['data' => $data]);

        // Gera o PDF com Dompdf
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();

        // Define o caminho para salvar o PDF
        $pdfPath = WRITEPATH . 'uploads/pdfs/' . uniqid(env('PROJECT_NAME')) . '.pdf';
        file_put_contents($pdfPath, $dompdf->output());

        // Salva o caminho e os dados no banco de dados
        $pdfId = $this->pdfModel->insert([
            'user_id' => $this->session->get('user_id'),
            'pdf_path' => $pdfPath,
            'data' => json_encode($data)
        ]);

        return $this->response->setJSON(['status' => 'success', 'message' => 'PDF gerado com sucesso', 'pdf_id' => $pdfId]);
    }

    public function download($pdf_id)
    {
        // Verifica se o usuário está autenticado
        if (!$this->session->has('user_id')) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Usuário não autenticado'])
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $pdfModel = new PdfModel();
        $pdf = $pdfModel->find($pdf_id);

        // Verifica se o PDF existe e pertence ao usuário
        if (!$pdf || $pdf->user_id !== $this->session->get('user_id')) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'PDF não encontrado ou acesso não autorizado'])
                ->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
        }

        // Fornece o PDF para download
        return $this->response->download($pdf->pdf_path, null)
            ->setFileName(env('PROJECT_NAME') . '.pdf');
    }
}
