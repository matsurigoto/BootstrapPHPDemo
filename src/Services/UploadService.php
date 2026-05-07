<?php
declare(strict_types=1);

namespace App\Services;

class UploadService
{
    public function __construct(private array $cfg) {}

    /**
     * 將上傳檔搬到目標目錄並回傳相對路徑。
     *
     * @param array{name:string,size:int,tmp_name:string,type:string,error:int} $file
     * @return array{path:string,name:string}
     */
    public function store(array $file, int $formId, int $responseId): array
    {
        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('上傳失敗 error=' . ($file['error'] ?? -1));
        }
        if ($file['size'] > ($this->cfg['max_size'] ?? 10485760)) {
            throw new \RuntimeException('檔案過大');
        }

        // MIME 雙重檢查 (finfo)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        $allowed = $this->cfg['allowed_mime'] ?? [];
        if ($allowed && !in_array($mime, $allowed, true)) {
            throw new \RuntimeException('不允許的檔案類型: ' . $mime);
        }

        $base = rtrim($this->cfg['path'], "/\\");
        $dir = $base . DIRECTORY_SEPARATOR . $formId . DIRECTORY_SEPARATOR . $responseId;
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('無法建立上傳目錄');
            }
        }
        $origName = preg_replace('/[^\p{L}\p{N}._\- ]+/u', '_', $file['name']);
        $safeName = bin2hex(random_bytes(6)) . '_' . substr($origName, 0, 80);
        $target = $dir . DIRECTORY_SEPARATOR . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('搬移上傳檔失敗');
        }
        $rel = 'uploads/' . $formId . '/' . $responseId . '/' . $safeName;
        return ['path' => $rel, 'name' => $file['name']];
    }
}
