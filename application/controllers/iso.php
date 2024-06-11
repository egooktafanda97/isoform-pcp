<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Iso extends CI_Controller
{
    public function index($uuid = null)
    {
        if ($uuid == null) {
            $this->db->order_by('name', 'ASC');
            $this->db->where('parent_id IS  NULL');
            $folderTree = $this->db->get('folders')->result_array();
        } else {
            $folderByUid = $this->db->get_where('folders', ['uuid' => $uuid])->row_array();
            $this->db->where('parent_id', $folderByUid['id']);
            $this->db->order_by('name', 'ASC');
            $folderTree = $this->db->get('folders')->result_array();
        }
        // files
        $this->db->where('folder_id', $folderByUid['id'] ?? null);
        $this->db->order_by('name', 'ASC');
        $files = $this->db->get('files')->result_array();

        // hak akses
        if ($this->session->userdata('user')) {
            $role = $this->db->get_where('roles', ['nama' => $this->session->userdata('user')['role']])->row_array();
            $permission = json_decode($role['permission'] ?? "[]", true);
            $access = json_decode($role['access'], true);
            $folderAccess = [];
            foreach ($access['folder'] as $f) {
                $folderAccess[] = $f['id'];
            }

            $fileAccess = [];
            foreach ($access['file'] as $file) {
                $fileAccess[] = $file['id'];
            }
        }


        // mapping folder
        $folders = [];
        foreach ($folderTree as $folder) {
            $newData = $folder;
            $newData['count_file'] = $this->db->get_where('files', ['folder_id' => $folder['id']])->num_rows();
            $newData['sum_file'] = $this->db->select_sum('size')->get_where('files', ['folder_id' => $folder['id']])->row_array()['size'] ?? 0;
            $folders[] = $newData;
        }

        // $folderByUid
        $tree = "";
        if (!empty($folderByUid['id'])) {
            $tree = $this->getTreePathFolder($folderByUid['id']);
        }

        $this->load->view('iso', [
            "folder" => $folders,
            "uuid" =>  $folderByUid['uuid'] ?? null,
            "files" => $files,
            "tree" => $tree ?? "",
            "permission" =>   $permission ?? [],
            "folderAccess" => $folderAccess ?? [],
            "fileAccess" =>   $fileAccess ?? [],
        ]);
    }
    // create folder
    public function createFolder()
    {
        if (!empty($this->input->post('folder-id'))) {
            // update name
            $this->db->update('folders', ['name' => $this->input->post('name')], ['id' => $this->input->post('folder-id')]);
            redirect('iso/index/' . $this->input->post('uuid'));
        }
        $this->db->insert('folders', [
            'name' => $this->input->post('name'),
            'icon' => 'default.png',
            'parent_id' => $this->db->get_where('folders', ['uuid' => $this->input->post('uuid')])->row_array()['id'] ?? null,
        ]);
        redirect('iso/index/' . $this->input->post('uuid'));
    }
    // deleteFolder
    public function deleteFolder($id)
    {
        $del =  $this->db->delete('folders', ['uuid' => $id]);
        if ($del) {
            echo json_encode(['status' => true]);
        } else {
            echo json_encode(['status' => false]);
        }
    }
    // createFolderIcon
    public function createFolderIcon()
    {
        $files =  uploaded('icon', 'public/icon/');
        if (!$files) {
            redirect('iso/index/' . $this->input->post(' uuid'));
        }
        $this->db->update('folders', ['icon' =>  $files['name']], ['id' => $this->input->post('folder-id')]);
        redirect('iso/index/' . $this->input->post('uuid'));
    }

    // upload file
    public function uploadFile()
    {
        $files =  uploaded('file', 'uploads/');
        if ($files) {
            $this->db->insert('files', [
                "name" => $this->input->post('file-name') ?? $files['name'],
                "paths" => $files['path'] . "/" . $files['name'],
                "folder_id" =>  $this->db->get_where('folders', ['uuid' => $this->input->post('uuid')])->row_array()['id'] ?? null,
                "type" => str_replace(".", "",  $files['type']),
                "size" => $files['size'],
            ]);
            redirect('iso/index/' . $this->input->post('uuid'));
        }
        // error
        // flashdata error
        redirect('iso/index/' . $this->input->post('uuid'));
    }

    public function getTreePathFolder($folderId)
    {
        $folder = $this->db->get_where('folders', ['id' => $folderId])->row_array();
        if ($folder['parent_id'] == null) {
            return '<li class="breadcrumb-item"> <a class="text-blue-400 " href="' . base_url('iso/index/' . $folder['uuid']) . '">' . $folder['name'] . '</a></li>';
        } else {
            return $this->getTreePathFolder($folder['parent_id']) . '<li class="breadcrumb-item"> <a class="text-blue-400 " href="' . base_url('iso/index/' . $folder['uuid']) . '">' . $folder['name'] . '</a></li>';
        }
    }



    // deleteFile ajax axios 
    public function deleteFile($id)
    {
        $del =  $this->db->delete('files', ['id' => $id]);
        if ($del) {
            echo json_encode(['status' => true]);
        } else {
            echo json_encode(['status' => false]);
        }
    }

    public function search()
    {
        $this->db->like('name', $_GET['query'] ?? '');
        $this->db->limit(10);
        $files = $this->db->get('files')->result_array();
        echo json_encode($files);
    }


    public function pushInArray()
    {
        $data = $this->xData();
        $newData = [];
        foreach ($data as $d) {
            $type = explode("/", $d['uri']);
            $type = explode(".", $type[count($type) - 1]);
            // cek folder
            $folder = $this->db->get_where('folders', ['name' => $d['name']])->row_array();
            if (empty($folder)) {
                // create folder 
                $this->db->insert('folders', [
                    'name' => $d['name'],
                    'icon' => 'default.png',
                    'parent_id' => null
                ]);
                $folderId = $this->db->insert_id();
            } else {
                $folderId = $folder['id'];
            }
            $this->db->insert('files', [
                "name" => $d['folder'],
                "uri" => $d['uri'],
                "folder_id" => $folderId,
                "type" => $type[count($type) - 1]
            ]);
        }
        // echo json_encode($newData);
    }

    public function permissionNumber()
    {
        return [
            "folder-create" => 1,
            "folder-delete" => 2,
            "folder-icon" => 3,
            "file-upload" => 4,
            "file-delete" => 5,
            "file-search" => 6,
        ];
    }

    // folder permission
    public function folderAccessPermission()
    {
        $data = [
            [
                "nama" => "superadmin",
                "permission" =>  json_encode([
                    $this->permissionNumber()['folder-create'],
                    $this->permissionNumber()['folder-delete'],
                    $this->permissionNumber()['folder-icon'],
                    $this->permissionNumber()['file-upload'],
                    $this->permissionNumber()['file-delete'],
                    $this->permissionNumber()['file-search'],
                ]),
                "access" => json_encode([
                    "folder" =>  $this->db->get('folders')->result_array(),
                    "file" => $this->db->get('files')->result_array(),
                ])
            ],
            [
                "nama" => "admin",
                "permission" =>  json_encode([
                    $this->permissionNumber()['folder-create'],
                    $this->permissionNumber()['folder-delete'],
                    $this->permissionNumber()['folder-icon'],
                    $this->permissionNumber()['file-upload'],
                    $this->permissionNumber()['file-delete'],
                    $this->permissionNumber()['file-search'],
                ]),
                "access" => json_encode([
                    "folder" =>  $this->db->get('folders')->result_array(),
                    "file" => $this->db->get('files')->result_array(),
                ])
            ],
            [
                "nama" => "user",
                "permission" => json_encode([]),
                "access" => json_encode([
                    "folder" => $this->db->get('folders')->result_array(),
                    "file" => $this->db->get('files')->result_array(),
                ])
            ],
        ];
        $this->db->insert_batch('roles', $data);
    }


    public function xData()
    {
        return  [
            [
                "name" => "Management ",
                "folder" => "Organizational Knowledge Mapping",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM005%20-%20Organizational%20Knowledge%20Mapping.xlsx",
            ],
            [
                "name" => "Management ",
                "folder" => "Pembuatan Perubahan Permintaan Informasi Terdokumentasi",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM011%20-%20Pembuatan-Perubahan-Permintaan-Informasi-Terdokumentasi.pdf",
            ],
            [
                "name" => "Management ",
                "folder" => "Form Risk Opportunity Assessment",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM001%20-%20Form-Risk-Opportunity-Assessment.xls",
            ],
            [
                "name" => "Management ",
                "folder" => "Form Sasaran Mutu",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM002%20-%20Form%20Sasaran%20Mutu.doc",
            ],
            [
                "name" => "Management ",
                "folder" => "Strategi Pencapaian Sasaran Mutu",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM003%20-%20Strategi%20Pencapaian%20Sasaran%20Mutu.xls",
            ],
            [
                "name" => "Management ",
                "folder" => "Change Notification (utk peruhanan kebijakan perusahaan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM004%20-%20Change%20Notification.xlsx",
            ],
            [
                "name" => "Management ",
                "folder" => "Daftar Pengetahuan Organisasi",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM005%20-Daftar%20Pengetahuan%20Oganisasi%20(20190827).xlsx",
            ],
            [
                "name" => "Management ",
                "folder" => "Tabel Komunikasi",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM006%20rev.01%20Tabel%20Komunikasi.pdf",
            ],
            [
                "name" => "Management ",
                "folder" => "Daftar Induk Informasi Terdokumentasi",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM008%20-Daftar%20Induk%20Informasi%20Terdokumentasi.doc",
            ],
            [
                "name" => "Management ",
                "folder" => "Daftar Informasi terdokumentasi Eksternal",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM009%20-%20Daftar%20Informasi%20Terdokumentasi%20Eksternal.doc",
            ],
            [
                "name" => "Management ",
                "folder" => "Daftar Informasi terdokumentasi",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM010%20-%20Daftar%20Informasi%20Terdokumentasi%20(Master).doc",
            ],
            [
                "name" => "Management ",
                "folder" => "Pembuatan Perubahan Permintaan Informasi",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM011%20-%20Pembuatan%20Perubahan%20Permintaan%20Informasi%20Terdokumentasi%20(20190827).xls",
            ],
            [
                "name" => "Management ",
                "folder" => "Tindakan Perbaikkan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM012%20-%20Tindakkan%20Perbaikkan.doc",
            ],
            [
                "name" => "Management ",
                "folder" => "Job Description",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/100124PCPOPS-PPFORM013%20rev.01%20Jobdesc.pdf",
            ],
            [
                "name" => "Management ",
                "folder" => "Laporan Ketidaksesuaian Audit Internal",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCP-OPS-PPFORM-014-Laporan%20Ketidak%20Sesuaian%20Audit%20Internal.pdf",
            ],
            [
                "name" => "Management ",
                "folder" => "Daftar Periksa Audit Internal",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM015-rev.01-Daftar-Periksa-Audit-Internal.doc",
            ],
            [
                "name" => "Management ",
                "folder" => "Rencana Audit Tahunan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM016%20Rencana%20Audit%20Tahunan%20(Th%202020)%2001.pdf",
            ],
            [
                "name" => "Management ",
                "folder" => "Jadwal Audit",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM017%20-%20Jadwal%20Audit%20(Master).doc",
            ],
            [
                "name" => "Management ",
                "folder" => "Notulen Meeting",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM018%20-%20Notulen%20Meeting.pdf",
            ],
            [
                "name" => "Management ",
                "folder" => "Summary Internal Quality Mutu",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM019%20-%20Summary%20Internal%20Quality%20Audit.docx",
            ],
            [
                "name" => "Management ",
                "folder" => "Matrix Referensi",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM020%20rev.05%20Matriks%20Referensi.pdf",
            ],
            [
                "name" => "Management ",
                "folder" => "Konteks Organisasi rev.01",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM021-%20Konteks%20Organisasi%20(20190827)%20(cek%20dgn%20risk%20&%20opportunity%20departemen%20terkait)%2020190905.doc",
            ],
            [
                "name" => "Management ",
                "folder" => "Daftar Distribusi Informasi Terdokumentasi",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM007-rev.01-Daftar-Distribusi-Informasi-Terdokumentasi.pdf",
            ],
            [
                "name" => "Management ",
                "folder" => "Berita Acara pemusnahan dokumen",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_management/PCPOPS-PPFORM022%20Berita%20Acara%20pemusnahan%20dokumen.docx",
            ],
            [
                "name" => "Commercial ",
                "folder" => "Survey Kepuasan Pelanggan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_komersial/PCPCOM-SLSFORM046%20rev.02%20Survey%20Kepuasan%20Pelanggan.pdf",
            ],
            [
                "name" => "Commercial ",
                "folder" => "Customer Registration Form (CRF)",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_komersial/PCPCOM-SLSFORM048%20rev.01%20Customer%20Registration%20Form.pdf",
            ],
            [
                "name" => "Commercial ",
                "folder" => "Form Permintaan pengembangan dan modifikasi product",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_komersial/PCPCOM-SLSFORM049%20rev.01%20Form%20Permintaan%20pengembangan%20dan%20modifikasi%20product.xlsx",
            ],
            [
                "name" => "Commercial ",
                "folder" => "Gap Kalkulasi & Analisa",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_komersial/PCPCOM-SLSFORM047%20rev.01%20Gap%20calculation%20analyst.xlsx",
            ],
            [
                "name" => "Commercial ",
                "folder" => "Lead Card",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_komersial/PCPCOM-SLSFORM050%20rev.01%20Lead%20Card.pdf",
            ],
            [
                "name" => "Commercial ",
                "folder" => "SOA",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_komersial/PCPCOM-SLSFORM051%20rev.06%20SOA.pdf",
            ],
            [
                "name" => "CCO ",
                "folder" => "Form Investigasi Kurir",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_cco/PCPOPS-CCOFORM054%20rev.01%20Form%20investigasi%20Kurir.pdf",
            ],
            [
                "name" => "CCO ",
                "folder" => "Form Customer Complaint Statement",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_cco/PCPOPS-CCOFORM052%20rev.02%20Customer%20Complaint%20Statement%20(CCS).pdf",
            ],
            [
                "name" => "CCO ",
                "folder" => "Form Laporan Hasil Investigasi Shipment Rusak Hilang",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_cco/PCPOPS-CCOFORM053%20rev.02%20Laporan%20Hasil%20Investigasi%20Shipment%20Rusak%20atau%20Hilang.pdf",
            ],
            [
                "name" => "CCO ",
                "folder" => "Form Klaim ke Simasnet",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_cco/PCPOPS-CCOFORM055%20rev.01%20Form%20Klaim%20ke%20Simasnet.pdf",
            ],
            [
                "name" => "Fleet ",
                "folder" => "Form Penggunaan Armada",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_fleet/PCPOPS-FOPFORM091%20rev.04%20Form%20Pemakaian%20Kendaraan.pdf",
            ],
            [
                "name" => "Fleet ",
                "folder" => "Form Serah Terima Armada",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_fleet/PCPOPS-FOPFORM092%20rev.03%20Form%20Serah%20Terima%20Kendaraan.pdf",
            ],
            [
                "name" => "Fleet ",
                "folder" => "Form Berita Acara Temua Kerusakan Armada",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_fleet/PCPOPS-FOPFORM093%20rev.01%20Berita%20Acara%20temuan%20kerusakkan%20kendaraan.pdf",
            ],
            [
                "name" => "Fleet ",
                "folder" => "Form Blanko Pengajuan Service Kendaraan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_fleet/PCPOPS-FOPFORM094%20rev.02%20Blanko%20Pengajuan%20Service%20Kendaraan.pptx",
            ],
            [
                "name" => "Fleet ",
                "folder" =>
                "Form Laporan Hasil Investigasi (LHI) Kecelakaan Armada (Laka)",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_fleet/PCPOPS-FOPFORM095%20rev.01%20Form%20Laporan%20Hasil%20Investigasi%20Kejadian%20Kecelakaan.docx",
            ],
            [
                "name" => "Fleet ",
                "folder" => "Form Jadwal Perawatan Kendaraan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_fleet/PCPOPS-FOPFORM096%20rev.%2001%20Jadwal%20Perawatan%20Kendaraan.pdf",
            ],
            [
                "name" => "Fleet ",
                "folder" => "Form Jadwal Kartu Sakit Kendaraan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_fleet/PCPOPS-FOPFORM097%20rev.02%20Kartu%20sakit%20kendaraan.pdf",
            ],
            [
                "name" => "Fleet ",
                "folder" => "Form Surat Perintah Kerja",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_fleet/PCPOPS-FOPFORM098%20rev.01%20Surat%20Perintah%20Kerja.pdf",
            ],
            [
                "name" => "Fleet ",
                "folder" => "Form Perbaikan Kendaraan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_fleet/PCPOPS-FOPFORM099%20rev.01%20Form%20Perbaikan%20Kendaraan.pdf",
            ],
            [
                "name" => "Fleet ",
                "folder" => "Form Kontrol Kendaraan Mingguan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_fleet/PCPOPS-FOPFORM100%20rev.02%20Kartu%20Kontrol%20Kendaraan%20Mingguan.xlsx",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form STBI",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM076%20rev.01%20Serah%20Terima%20Barang%20Inbound%20(STBI).pdf",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form Laporan absensi moda darat Jakarta-Surabaya",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM077%20rev.02%20Laporan%20absensi%20moda%20darat%20Jakarta-Surabaya.xlsx",
            ],
            [
                "name" => "Freight ",
                "folder" => "Evaluasi Vendor FreIght Ops",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM078%20rev.01%20Evaluasi%20Vendor%20FreIght%20Ops.pdf",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form Feadback Vendor Freight OPS (20190827)",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM079%20rev.01%20Form%20Feadback%20Vendor%20Freight%20OPS.pdf",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form Serah Terima Barang Darat (STBD)",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM080%20rev.01%20Form%20STBD%20(%20Serah%20Terima%20Barang%20Darat%20).pdf",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form Laporan absensi moda darat Jakarta-Pekanbaru",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM081%20rev.01%20Laporan%20absensi%20moda%20darat%20Jakarta-Pekanbaru.xlsx",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form Laporan absensi moda darat Jakarta-Bandung",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM082%20rev.01%20Laporan%20absensi%20moda%20darat%20Jakarta-Bandung.xlsx",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form Laporan absensi moda darat Medan-Pekanbaru",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM083%20rev.01%20Laporan%20absensi%20moda%20darat%20Medan-Pekanbaru.xlsx",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form Laporan absensi moda darat Bandung-Solo",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM084%20rev.01%20Laporan%20absensi%20moda%20darat%20Bandung-Solo.xlsx",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form Laporan absensi moda darat Surabaya-Jogjakarta",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM085%20rev.01%20Laporan%20absensi%20moda%20darat%20Surabaya-Jogjakarta.xlsx",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form Laporan absensi moda darat Surabaya-Denpasar",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM086%20rev.01%20Laporan%20absensi%20moda%20darat%20Surabaya-Denpasar.xlsx",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form Laporan absensi moda darat Surabaya-Malang",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM087%20rev.01%20Laporan%20absensi%20moda%20darat%20Surabaya-Malang.xlsx",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form Laporan absensi moda darat Semarang-Solo",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM088%20rev.01%20Laporan%20absensi%20moda%20darat%20Semarang-Solo.xlsx",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form Laporan absensi moda darat Semarang-Cirebon",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM089%20rev.01%20Laporan%20absensi%20moda%20darat%20Semarang-Cirebon.xlsx",
            ],
            [
                "name" => "Freight ",
                "folder" => "Form Berita Acara Temuan Segel",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_freight/PCPOPS-FOPFORM090%20rev.01%20Form%20Berita%20Acara%20Temuan%20Segel.pdf",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Checklist Pemeriksaan Sepeda Motor",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM070%20rev.02%20Cheklist%20Sepeda%20Motor.pdf",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Form Manifest Manual",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM067%20rev.01%20Form%20Manifest%20Manual.pdf",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Cek list forklif",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM068%20rev.01%20Cek%20list%20forklif.pdf",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Form Petty Cash Kurir",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM066%20rev.02%20Petty%20Cash%20Kurir.pdf",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Form STBO",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM056%20rev.02%20Form%20Serah%20Terima%20Barang%20Outbound%20(Group).xls",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Form Berita Acara Temuan Kejadian",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM059%20rev.01%20Berita%20Acara%20Temuan%20Kejadian.pdf",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Form Manual Serah Terima Pick Up (Pick Up Sheet)",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM064%20rev.01%20Form%20Serah%20Terima%20Hasil%20Pick%20Up.pdf",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Form Tally Sheet (Barang Bongkar Muat)",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM065%20rev.01%20Tally%20Sheet%20barang%20bongkar%20muat.pdf",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Laporan Setoran Harian Cash Register",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM057%20rev.01%20Laporan%20Setoran%20Harian%20Cash%20Register.pdf",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Control Plan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM058%20rev.01%20Control%20Plan%20PCP.xltx",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Handover",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM060%20rev.01%20Handover.pdf",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Manifest",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM061%20rev.01%20Manifest.pdf",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Delivery Record Assignment",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM062%20rev.01%20Delivery%20Record%20Assignment.pdf",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Pick Up Confirmation",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM063%20rev.01%20Pick%20Up%20Confirmation.pdf",
            ],
            [
                "name" => "Ground Ops ",
                "folder" => "Form Serah Terima Barang Outbound (Detail)",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ground_ops/PCPOPS-GOPFORM069%20rev.01%20Serah%20Terima%20Barang%20Outbound%20(Detail).xls",
            ],
            [
                "name" => "Network ",
                "folder" => "Form Calon Mitra Keagenan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_network/PCPOPS-BIDFORM132%20rev.02%20Formulir%20Calon%20mitra%20keagenan.xlsx",
            ],
            [
                "name" => "Network ",
                "folder" => "Form Berita Acara Koordinasi Satelit & Agen",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_network/PCPOPS-BIDFORM134%20rev.01%20Berita%20Acara%20Koordinasi%20Satelit%20%20Agen.xlsx",
            ],
            [
                "name" => "Network ",
                "folder" => "Formulir Evaluasi Kinerja",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_network/PCPOPS-BIDFORM133%20rev.02%20Formulir%20Evaluasi%20Kinerja.xlsx",
            ],
            [
                "name" => "PUSDATIN ",
                "folder" => "Form Serah Terima AWB",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_pusdatin/PCPOPS-OSAFORM073%20rev.01%20Form%20Serah%20Terima%20AWB.xlsx",
            ],
            [
                "name" => "PUSDATIN ",
                "folder" => "Form Disposisi PCP",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_pusdatin/PCPOPS-OSAFORM072%20rev.01%20Form%20Disposisi%20pcp.pdf",
            ],
            [
                "name" => "PUSDATIN ",
                "folder" => "Form Serah Terima Pusdatin ke Processing & Billing",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_pusdatin/PCPOPS-OSAFORM071%20rev.02%20Form%20Serah%20Terima%20Pusdatin%20ke%20Processing%20&%20Billing.xlsx",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Absensi",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-RPDFORM023%20rev.01%20Form%20Absensi.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form PUK General Mid Up",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-CGAFORM024%20rev.01%20Penilaian%20Unjuk%20Kerja.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Permintaan Tenaga Kerja",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-RPDFORM025%20rev.01%20Form%20Permintaan%20tenaga%20kerja.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Bio Data Karyawan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-RPDFORM027%20rev.01%20Formulir%20Biodata%20kandidat.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Permintaan Pelatihan & Training",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-RPDFORM028%20rev.01%20Permintaan%20Pelatihan%20&%20Training.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Umpan Balik Pelatihan & Training",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-RPDFORM029%20rev.03%20Form%20Umpan%20Balik%20Pelatihan%20Training.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Renumerasi Promosi rotasi Mutasi Demosi",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/051223PCPHRD-CGAFORM031%20rev.03%20Persetujuan%20Renumerasi-Promosi-Rotasi-Mutasi-Demosi.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Matriks Kompetensi Commercial",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-RPDFORM032%20rev.01%20Matriks%20Kompetensi.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Data Pelatihan Karyawan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-RPDFORM033%20rev.01%20Data%20Pelatihan%20Karyawan.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Hasil Unjuk Kerja Karyawan Baru",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/041223PCPHRD-CGAFORM034%20rev.02%20%20Form%20Hasil%20Unjuk%20Kerja%20karyawan%20baru.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Exit Check List HRD Terbaru",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-CGAFORM035%20rev.03%20Formulir%20Exit%20Check%20List%20HRD.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Penilaian Hasil Interview",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-RPDFORM036%20rev.02%20Form%20Penilaian%20Hasil%20Interview.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Surat Perintah Lembur",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-CGAFORM037%20rev.01%20Form%20Surat%20perintah%20lembur.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Jadwal Pelatihan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-RPDFORM038%20rev.01%20Susunan%20ACara%20Pelatihan.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Penilaian Efektivitas Pelatihan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-RPDFORM039%20rev.01%20Penilaian%20Efektivitas%20Pelatihan.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Program Pelatihan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-RPDFORM040%20rev.01%20Program%20Pelatihan.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form SPPD Original",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-CGAFORM041%20rev.01%20Permohonan%20uang%20muka%20perjalanan%20dinas.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Surat Perintah Piket",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-CGAFORM042%20rev.01%20Form%20Surat%20Perintah%20Piket.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Izin",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-CGAFORM043%20rev.01%20Form%20Izin.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "DISC Test",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-RPDFORM030%20rev.01%20DISC%20Test.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Form Permohonan Cuti",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-CGAFORM044%20rev.01%20Permohonan%20Cuti.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Daftar Training Mandatori",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-RPDFORM045%20rev.01%20Training%20Mandatori.pdf",
            ],
            [
                "name" => "Human Resources Department / HRD ",
                "folder" => "Formulir Serah Terima Penahanan Ijazah",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_hrd/PCPHRD-CGAFORM148%20rev.00%20Formulir%20Serah%20Terima%20Penahanan%20Ijazah.doc",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Form Permintaan Perbaikan Infrastruktur",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM114%20rev.02%20Form%20Permintaan%20Perbaikan%20Infrastruktur.pdf",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Form Monitoring Kebersihan Toilet",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM115%20rev.01%20Form%20Monitoring%20Kebersihan.pdf",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Form Monitoring AC",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM116%20rev.01%20Kartu%20Pemeliharaan%20Air%20Conditioner.pdf",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Form Monitoring Pemeliharaan Taman",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM117%20rev.01%20Jadwal%20pemeliharaan%20taman.pdf",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Jadwal Perawatan Mesin Infrastruktur",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM120%20rev.01%20Jadwal%20Perawatan%20Mesin%20Infrastruktur.pdf",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Form Monitoring Kebersihan Gudang",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM123%20rev.01%20Monitoring%20Kebersihan.pdf",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Jadwal Monitoring Pest Control & Infrastruktur",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM122%20rev.01%20Jadwal%20Monitoring%20Pest%20Control%20&%20Infrastruktur.pdf",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Jadwal Kalibrasi",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM121%20rev.01%20Jadwal%20Kalibrasi.pdf",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Daftar Peralatan Yang Perlu Dikalibrasi",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM124%20rev.01%20Daftar%20alat%20yang%20dikalibrasi.pdf",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Fixed Asset perusahaan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM118%20rev.01%20Form%20Fixed%20Asset%20PCP.xlsx",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Laporan Hasil Verifikasi Alat Ukur",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM125%20rev.01%20Laporan%20Hasil%20Verifikasi%20Alat%20Ukur.pdf",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Formulir Perawatan Infrastruktur bulanan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM119%20rev.01%20Form%20Perawatan%20Infrastruktur%20bulanan.pdf",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Laporam Hasil Pemeriksaan Timbangan",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM126%20rev.01%20Laporan%20Hasil%20Pemeriksaan%20Timbangan.pdf",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Laporan Hasil Pemeriksaan Alat Ukur",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM130%20rev.01%20Laporan%20Hasil%20Pemeriksaan%20Alat%20Ukur.pdf",
            ],
            [
                "name" => "General Affair / GA ",
                "folder" => "Formulir Permintaan Stock Barang",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ga/PCPHRD-CGAFORM131%20rev.01%20Permintaan%20Stock%20Barang.pdf",
            ],
            [
                "name" => "Keuangan ",
                "folder" => "Petty Cash",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_keuangan/PCPFIN-TRYFORM103%20rev.01%20Petty%20Cash.pdf",
            ],
            [
                "name" => "Keuangan ",
                "folder" => "Tanda Terima Invoice dan Faktur Pajak",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_keuangan/PCPFIN-BCLFORM101%20rev.01%20Tanda%20Terima%20Invoice%20dan%20Faktur%20Pajak.xlsx",
            ],
            [
                "name" => "Keuangan ",
                "folder" => "Tanda Terima Invoice Collect",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_keuangan/PCPFIN-BCLFORM102%20rev.01%20Tanda%20Terima%20Invoice%20Collect.xlsx",
            ],
            [
                "name" => "Keuangan ",
                "folder" => "Surat Peringatan Satu",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_keuangan/PCPFIN-BCLFORM158%20rev.00%20Surat%20Peringatan%20Satu.docx",
            ],
            [
                "name" => "Keuangan ",
                "folder" => "Surat Peringatan Dua",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_keuangan/PCPFIN-BCLFORM159%20rev.00%20Surat%20Peringatan%20Dua%20.docx",
            ],
            [
                "name" => "Keuangan ",
                "folder" => "Surat Permohonan Pembayaran",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_keuangan/PCPFIN-BCLFORM157%20rev.00%20Surat%20Permohonan%20Pembayaran.docx",
            ],
            [
                "name" => "Keuangan ",
                "folder" => "Surat Pemberitahuan Outstanding",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_keuangan/PCPFIN-BCLFORM156%20rev.00%20Surat%20Pemberitahuan%20Outstanding.docx",
            ],
            [
                "name" => "Purchase ",
                "folder" => "Form Kuesioner Kualifikasi Vendor Baru PCP Express",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_purchase/PCPFIN-PRCFORM104%20rev.02%20%20Kuisioner%20kualifikasi%20vendor%20baru.pdf",
            ],
            [
                "name" => "Purchase ",
                "folder" => "Form Evaluasi Vendor PCP Expresst",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_purchase/PCPFIN-PRCFORM105%20rev.%2001%20Evaluasi%20Vendor%20Supplier.pdf",
            ],
            [
                "name" => "Purchase ",
                "folder" => "Form Permintaan Barang Logistik",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_purchase/PCPFIN-PRCFORM106%20rev.01%20Form%20Permintaan%20Barang%20Cetakan%20&%20Logistik.pdf",
            ],
            [
                "name" => "Purchase ",
                "folder" => "Form PD PCP Express Standart",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_purchase/PCPFIN-PRCFORM108%20rev.03%20Persetujuan%20Pengeluaran%20Dana.xlsx",
            ],
            [
                "name" => "Purchase ",
                "folder" => "Form PO PCP Express Standart",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_purchase/PCPFIN-PRCFORM109%20rev.%2002%20Purchase%20Order.docx",
            ],
            [
                "name" => "Purchase ",
                "folder" => "Form Laporan Penerimaan Barang",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_purchase/PCPFIN-PRCFORM107%20rev.03%20Laporan%20Penerimaan%20Barang.docx",
            ],
            [
                "name" => "Purchase ",
                "folder" => "Form Persetujuan Pengadaan Barang Modal",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_purchase/PCPFIN-PRCFORM110%20%20rev.01%20Persetujuan%20Pengadaan%20Barang%20Modal.pdf",
            ],
            [
                "name" => "Purchase ",
                "folder" => "Purchase Order",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_purchase/PCPFIN-PRFORM006-rev.01-Purchase-Order.docx",
            ],
            [
                "name" => "Purchase ",
                "folder" => "Rekapitulasi Evaluasi Vendor",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_purchase/PCPFIN-PRCFORM111%20rev.01%20%20Rekapitulasi%20Evaluasi%20Vendor.pdf",
            ],
            [
                "name" => "Purchase ",
                "folder" => "Approved Vendor List",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_purchase/PCPFIN-PRCFORM112%20rev.01%20Approved%20Vendor%20List%20(AVL).pdf",
            ],
            [
                "name" => "Purchase ",
                "folder" => "Form Supplier Feedback Review",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_purchase/PCPFIN-PRCFORM113%20rev.01%20%20Supplier%20Feedback%20Review.pdf",
            ],
            [
                "name" => "Purchase ",
                "folder" => "Konfirmasi Sewa Gedung",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_purchase/PCPFIN-PRCFORM152%20rev.00%20Konfirmasi%20Sewa%20Gedung.doc",
            ],
            [
                "name" => "Information, Communication & Technology / ICT ",
                "folder" => "Form Jadwal Backup Server",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ict/PCPOPS-ICTFORM136%20rev.01%20Jadwal%20Backup%20Server.xlsx",
            ],
            [
                "name" => "Information, Communication & Technology / ICT ",
                "folder" => "Form Jadwal Update Antivirus",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ict/PCPOPS-ICTFORM137%20rev.02%20Jadwal%20Update%20Antivirus.xlsx",
            ],
            [
                "name" => "Information, Communication & Technology / ICT ",
                "folder" => "Form Pencatatan Perbaikan Komputer",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ict/PCPOPS-ICTFORM139%20rev.01%20Pencatatan%20Perbaikan%20Komputer.pdf",
            ],
            [
                "name" => "Information, Communication & Technology / ICT ",
                "folder" => "Formulir Monitoring Kamera CCTV",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ict/PCPOPS-ICTFORM143%20rev.01%20Formulir%20Monitoring%20Kamera%20CCTV.xlsx",
            ],
            [
                "name" => "Information, Communication & Technology / ICT ",
                "folder" => "Perbaikkan Komputer",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ict/PCPOPS-ICTFORM138%20rev.01%20Perbaikan%20Komputer.pdf",
            ],
            [
                "name" => "Information, Communication & Technology / ICT ",
                "folder" => "Form Feedback balik Vendor ICT",
                "uri" =>
                "https://pcpexpress.com/isoform/files/form_ict/PCPOPS-ICTFORM141%20rev.01%20Vendor%20Feedback%20Review.docx",
            ]
        ];
    }
}
// "https://pcpexpress.com/isoform/files/form_ict/PCPOPS-ICTFORM141%20rev.01%20Vendor%20Feedback%20Review.docx", get type file in url
