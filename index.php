<?php

function getHTML() {
    $url = "http://124.158.186.234/dokumen/perdata";
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: Mozilla/5.0\r\n"
        ]
    ];
    return @file_get_contents($url, false, stream_context_create($opts)) ?: '';
}

function getData() {

    $html = getHTML();
    if (!$html) return [];

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();

    $rows = $dom->getElementsByTagName('tr');
    $data = [];

    foreach ($rows as $row) {

        $cols = $row->getElementsByTagName('td');

        if ($cols->length > 0) {

            $nomor = trim($cols->item(1)->nodeValue ?? '');
            $pihak = trim($cols->item(4)->nodeValue ?? '');
            $status = trim($cols->item(6)->nodeValue ?? '');

            if ($status !== "Sidang pertama" && $status !== "Persidangan") continue;

            $penggugat = '';
            $tergugat  = '';
            $pemohon   = '';
            $tipe      = '';

            if (strpos($pihak, 'Penggugat:') !== false) {
                $tipe = 'gugat';
                preg_match('/Penggugat:(.*?)Tergugat:/s', $pihak, $g);
                preg_match('/Tergugat:(.*)/s', $pihak, $t);
                $penggugat = trim($g[1] ?? '');
                $tergugat  = trim($t[1] ?? '');
            }
            elseif (strpos($pihak, 'Termohon:') !== false) {
                $tipe = 'talak';
                preg_match('/Pemohon:(.*?)Termohon:/s', $pihak, $p);
                preg_match('/Termohon:(.*)/s', $pihak, $t);
                $penggugat = trim($p[1] ?? '');
                $tergugat  = trim($t[1] ?? '');
            }
            elseif (strpos($pihak, 'Pemohon:') !== false) {
                $tipe = 'permohonan';
                preg_match('/Pemohon:(.*)/s', $pihak, $p);
                $pemohon = trim($p[1] ?? '');
                $pemohon = preg_replace('/(\d+\.)/', ' | $1', $pemohon);
                $pemohon = preg_replace('/\s+/', ' ', $pemohon);
                $pemohon = ltrim($pemohon, ' |');
            }

            $data[] = [
                'nomor' => $nomor,
                'penggugat' => preg_replace('/\s+/', ' ', $penggugat),
                'tergugat' => preg_replace('/\s+/', ' ', $tergugat),
                'pemohon' => $pemohon,
                'status' => $status,
                'tipe' => $tipe
            ];
        }
    }

    return $data;
}

if (isset($_GET['term'])) {

    header('Content-Type: application/json');

    $data = getData();
    $q = strtolower($_GET['term']);
    $result = [];

    foreach ($data as $item) {
        if (strpos(strtolower($item['nomor']), $q) !== false) {
            $result[] = [
                'label' => $item['nomor'] . " (" . $item['status'] . ")",
                'value' => $item['nomor'],
                'penggugat' => $item['penggugat'],
                'tergugat' => $item['tergugat'],
                'pemohon' => $item['pemohon'],
                'tipe' => $item['tipe']
            ];
        }
    }

    echo json_encode($result);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Form Saksi</title>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

<!-- LIBRARY SELECT2 UNTUK DROPDOWN PENCARIAN -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
body {
    font-family: Arial;
    padding: 20px;
    margin: 0;
}

.container {
    max-width: 500px;
    margin: 0 auto; 
    text-align: center; 
}

.form-group {
    margin-bottom: 12px;
    text-align: left; 
}

label {
    display: block;
    margin-bottom: 4px;
}

input, select, textarea {
    padding: 8px;
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #ccc;
    outline: none;
    transition: border-color 0.3s;
}

#cari {
    max-width: 500px;
    margin-bottom: 20px;
}

.box {
    width: 100%;
    padding: 15px;
    border: 1px solid #ccc;
    display: none;
    box-sizing: border-box;
    margin-bottom: 20px;
}

.saksi-box {
    border: 1px solid #ccc;
    padding: 15px;
    margin-bottom: 20px;
    box-sizing: border-box;
    background-color: white;
    text-align: left;
}

.saksi-header {
    font-weight: bold; 
    margin-bottom: 15px; 
    border-bottom: 1px solid #ccc; 
    padding-bottom: 10px;
    cursor: pointer;
}

#listSaksiContainer {
    width: 100%;
    display: none;
}

button {
    padding: 8px 12px;
    margin-top: 10px;
    cursor: pointer;
}

.ui-datepicker {
    font-size: 14px;
}

.req {
    color: red;
}

/* Menyesuaikan desain Select2 agar polos seperti input biasa */
.select2-container .select2-selection--single {
    height: 35px !important;
    border: 1px solid #ccc !important;
    border-radius: 0px !important;
    outline: none;
    transition: border-color 0.3s;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 33px !important;
    padding-left: 8px !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 33px !important;
}
</style>

</head>
<body>

<div class="container">

<h2>Cari Nomor Perkara</h2>

<input type="text" id="cari" placeholder="Ketik nomor perkara...">

<!-- Kotak Data Perkara -->
<div class="box" id="formBox">

    <div class="form-group">
        <label>Nomor Perkara:</label>
        <input type="text" id="nomor" readonly style="background: #eee;">
    </div>

    <div id="box_penggugat" class="form-group">
        <label id="label1"></label>
        <input type="text" id="penggugat" readonly style="background: #eee;">
    </div>

    <div id="box_tergugat" class="form-group">
        <label id="label2"></label>
        <input type="text" id="tergugat" readonly style="background: #eee;">
    </div>

    <div id="box_pemohon" class="form-group">
        <label>Pemohon:</label>
        <input type="text" id="pemohon" readonly style="background: #eee;">
    </div>

    <button type="button" onclick="tambahSaksi()" style="width: 100%;">+ Tambah Saksi</button>

</div>

<!-- Area Form Saksi -->
<div id="listSaksiContainer">
    <div id="listSaksi"></div>
    <button type="button" onclick="simpanSemua()" style="width:100%; font-weight:bold; padding:12px; margin-bottom: 40px; background-color: #eee; border: 1px solid #ccc; color: black;">Simpan Semua Data</button>
</div>

</div>

<script>
let noSaksi = 0;
const API_URL = "https://www.emsifa.com/api-wilayah-indonesia/api/";

// Fungsi untuk mengubah huruf KAPITAL menjadi Title Case (Huruf Awal Besar)
function toTitleCase(str) {
    if (!str) return '';
    return str.toLowerCase().replace(/\b\w/g, function(s) {
        return s.toUpperCase();
    });
}

// Fungsi Menghitung Umur
function hitungUmur(tglLahirStr) {
    if (!tglLahirStr) return '';
    let parts = tglLahirStr.split(/[\/\-]/); 
    if (parts.length !== 3) return '';
    
    let day = parseInt(parts[0], 10);
    let month = parseInt(parts[1], 10) - 1; 
    let year = parseInt(parts[2], 10);

    let dob = new Date(year, month, day);
    let today = new Date();
    
    let age = today.getFullYear() - dob.getFullYear();
    let m = today.getMonth() - dob.getMonth();
    
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
        age--;
    }
    
    return isNaN(age) || age < 0 ? '' : age;
}

// FUNGSI BARU: Mengurutkan ulang nomor saksi di layar secara otomatis
function updateUrutanSaksi() {
    let urutan = 1;
    $(".saksi-box").each(function() {
        // Cari id unik dari elemen yang ada di box ini
        let idUnik = $(this).find(".input-nama-saksi").data("id");
        let namaInput = $(this).find(".input-nama-saksi").val();
        
        if(namaInput.trim() === "") {
            $("#title_saksi_" + idUnik).text("Identitas Saksi " + urutan);
        } else {
            $("#title_saksi_" + idUnik).text("Saksi " + urutan + " - " + namaInput);
        }
        urutan++;
    });
}

function toggleSaksi(id) {
    $("#saksi_body_" + id).slideToggle();
}

function hapusSaksi(id, event) {
    event.stopPropagation(); // Mencegah form tertutup/buka saat tombol batal diklik
    $("#saksi_" + id).remove();
    
    if ($(".saksi-box").length === 0) {
        $("#listSaksiContainer").hide();
        noSaksi = 0; // Reset penomoran internal ke 0 karena sudah kosong total
    } else {
        updateUrutanSaksi(); // Urutkan ulang sisanya
    }
}

// LOGIKA VALIDASI SIMPAN
function simpanSemua() {
    let isValid = true;
    
    if ($(".saksi-box").length === 0) {
        alert("Belum ada data saksi yang ditambahkan.");
        return;
    }

    $(".req-input").each(function() {
        if ($(this).val() == null || $(this).val().toString().trim() === "") {
            // Khusus untuk elemen select2, beri warna merah pada containernya
            if($(this).hasClass("select2-hidden-accessible")) {
                $(this).next('.select2-container').find('.select2-selection').css("border", "2px solid red");
            } else {
                $(this).css("border", "2px solid red");
            }
            isValid = false;
        } else {
            if($(this).hasClass("select2-hidden-accessible")) {
                $(this).next('.select2-container').find('.select2-selection').css("border", "1px solid #ccc");
            } else {
                $(this).css("border", "1px solid #ccc");
            }
        }
    });

    $(".select-pekerjaan").each(function() {
        if ($(this).val() === "2") { 
            let inputLain = $(this).siblings(".input-pekerjaan-lain");
            if (inputLain.val().trim() === "") {
                inputLain.css("border", "2px solid red");
                isValid = false;
            }
        }
    });

    if (!isValid) {
        alert("Mohon lengkapi semua data wajib (kolom dengan bintang merah) sebelum menyimpan!");
        return;
    }

    alert("Semua data lengkap dan valid! Siap diproses ke server.");
}

// Fungsi inisialisasi Select2 yang memaksa kursor (autofocus)
function initSelect2Focus(elementId) {
    $(elementId).select2({ width: '100%' }).on('select2:open', function() {
        setTimeout(function() {
            let searchField = document.querySelector('.select2-container--open .select2-search__field');
            if (searchField) {
                searchField.focus();
            }
        }, 100); 
    });
}

function tambahSaksi() {
    noSaksi++;
    $("#listSaksiContainer").show();
    
    $(".saksi-body").slideUp();
    
    let formHTML = `
        <div class="saksi-box" id="saksi_${noSaksi}">
            
            <div class="saksi-header" onclick="toggleSaksi(${noSaksi})">
                <span id="title_saksi_${noSaksi}">Identitas Saksi</span>
                <button type="button" onclick="hapusSaksi(${noSaksi}, event)" style="float:right; margin-top:-8px; padding:4px 8px;">Batal</button>
            </div>
            
            <div class="saksi-body" id="saksi_body_${noSaksi}" style="display:block;">
                <div class="form-group">
                    <label>Jenis Pihak <span class="req">*</span></label>
                    <select name="saksi[${noSaksi}][jenis_pihak]" class="req-input">
                        <option value="">Pilih Jenis Pihak</option>
                        <option value="1">Perorangan</option>
                        <option value="3">Badan Hukum</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nama <span class="req">*</span></label>
                    <input type="text" name="saksi[${noSaksi}][nama]" class="input-nama-saksi req-input" data-id="${noSaksi}">
                </div>
                <div class="form-group">
                    <label>Tempat Lahir</label>
                    <input type="text" name="saksi[${noSaksi}][tempat_lahir]">
                </div>
                <div class="form-group">
                    <label>Tanggal Lahir <span class="req">*</span></label>
                    <input type="text" class="input-tgl req-input" id="tgl_lahir_${noSaksi}" name="saksi[${noSaksi}][tgl_lahir]" placeholder="dd/mm/yyyy" readonly style="cursor:pointer; background:white;">
                </div>
                <div class="form-group">
                    <label>Umur <span class="req">*</span></label>
                    <input type="text" class="input-umur req-input" name="saksi[${noSaksi}][umur]" readonly style="background:#eee;">
                </div>
                <div class="form-group">
                    <label>Jenis Identitas</label>
                    <select name="saksi[${noSaksi}][jenis_identitas]">
                        <option value="">Pilih Identitas</option>
                        <option value="1">Kartu BPJS</option>
                        <option value="2">Kartu Keluarga</option>
                        <option value="3">Kartu Pelajar</option>
                        <option value="4">KTP</option>
                        <option value="5">Paspor</option>
                        <option value="6">SIM</option>
                        <option value="7">KTA</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>No Identitas</label>
                    <input type="text" name="saksi[${noSaksi}][no_identitas]">
                </div>
                <div class="form-group">
                    <label>No Telepon</label>
                    <input type="text" name="saksi[${noSaksi}][no_tlp]">
                </div>
                <div class="form-group">
                    <label>Alamat Email</label>
                    <input type="text" name="saksi[${noSaksi}][email]">
                </div>
                <div class="form-group">
                    <label>Alamat <span class="req">*</span></label>
                    <textarea name="saksi[${noSaksi}][alamat]" class="req-input" rows="3"></textarea>
                </div>
                
                <!-- DROPDOWN WILAYAH DENGAN SELECT2 -->
                <div class="form-group">
                    <label>Provinsi <span class="req">*</span></label>
                    <select name="saksi[${noSaksi}][prop]" id="prov_${noSaksi}" class="select-prov req-input" data-id="${noSaksi}">
                        <option value="">- Cari/Pilih Propinsi -</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kabupaten/Kota <span class="req">*</span></label>
                    <select name="saksi[${noSaksi}][kab]" id="kab_${noSaksi}" class="select-kab req-input" data-id="${noSaksi}">
                        <option value="">- Cari/Pilih Kabupaten/Kota -</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kecamatan <span class="req">*</span></label>
                    <select name="saksi[${noSaksi}][kec]" id="kec_${noSaksi}" class="select-kec req-input" data-id="${noSaksi}">
                        <option value="">- Cari/Pilih Kecamatan -</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kelurahan/Desa <span class="req">*</span></label>
                    <select name="saksi[${noSaksi}][kel]" id="kel_${noSaksi}" class="select-kel req-input" data-id="${noSaksi}">
                        <option value="">- Cari/Pilih Kelurahan/Desa -</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Jenis Kelamin <span class="req">*</span></label>
                    <select name="saksi[${noSaksi}][jenis_kelamin]" class="req-input">
                        <option value="">Pilih Jenis Kelamin</option>
                        <option value="1">Laki Laki</option>
                        <option value="2">Perempuan</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Agama <span class="req">*</span></label>
                    <select name="saksi[${noSaksi}][agama]" class="req-input">
                        <option value="">Pilih Agama</option>
                        <option value="4">Budha</option><option value="5">Hindu</option><option value="1">Islam</option><option value="3">Katolik</option><option value="7">Kong Hu Cu</option><option value="6">Lainnya</option><option value="2">Protestan</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Warga Negara <span class="req">*</span></label>
                    <select name="saksi[${noSaksi}][warga_negara]" id="warga_${noSaksi}" class="req-input">
                        <option value="16">Aaland Islands</option><option value="3">Afghanistan</option><option value="6">Albania</option><option value="60">Algeria</option><option value="1">Andorra</option><option value="9">Angola</option><option value="5">Anguilla</option><option value="10">Antarctica</option><option value="4">Antigua &amp; Barbuda</option><option value="11">Argentina</option><option value="7">Armenia</option><option value="15">Aruba</option><option value="14">Australia</option><option value="13">Austria</option><option value="17">Azerbaijan</option><option value="32">Bahamas</option><option value="24">Bahrain</option><option value="20">Bangladesh</option><option value="19">Barbados</option><option value="35">Belarus</option><option value="21">Belgium</option><option value="36">Belize</option><option value="26">Benin</option><option value="28">Bermuda</option><option value="33">Bhutan</option><option value="30">Bolivia</option><option value="18">Bosnia &amp; Herzegovina</option><option value="34">Botswana</option><option value="31">Brazil</option><option value="75">Britain (UK)</option><option value="103">British Indian Ocean Territory</option><option value="29">Brunei</option><option value="23">Bulgaria</option><option value="22">Burkina Faso</option><option value="25">Burundi</option><option value="114">Cambodia</option><option value="46">Cameroon</option><option value="37">Canada</option><option value="51">Cape Verde</option><option value="121">Cayman Islands</option><option value="40">Central African Rep.</option><option value="210">Chad</option><option value="45">Chile</option><option value="47">China</option><option value="52">Christmas Island</option><option value="38">Cocos (Keeling) Islands</option><option value="48">Colombia</option><option value="116">Comoros</option><option value="39">Congo (Dem. Rep.)</option><option value="41">Congo (Rep.)</option><option value="44">Cook Islands</option><option value="49">Costa Rica</option><option value="43">Cote d'Ivoire</option><option value="95">Croatia</option><option value="50">Cuba</option><option value="53">Cyprus</option><option value="54">Czech Republic</option><option value="57">Denmark</option><option value="56">Djibouti</option><option value="58">Dominica</option><option value="59">Dominican Republic</option><option value="216">East Timor</option><option value="61">Ecuador</option><option value="63">Egypt</option><option value="206">El Salvador</option><option value="86">Equatorial Guinea</option><option value="65">Eritrea</option><option value="62">Estonia</option><option value="67">Ethiopia</option><option value="70">Falkland Islands</option><option value="72">Faroe Islands</option><option value="69">Fiji</option><option value="68">Finland</option><option value="73">France</option><option value="78">French Guiana</option><option value="172">French Polynesia</option><option value="211">French Southern &amp; Antarctic Lands</option><option value="74">Gabon</option><option value="83">Gambia</option><option value="77">Georgia</option><option value="55">Germany</option><option value="80">Ghana</option><option value="81">Gibraltar</option><option value="87">Greece</option><option value="82">Greenland</option><option value="76">Grenada</option><option value="85">Guadeloupe</option><option value="90">Guam</option><option value="89">Guatemala</option><option value="79">Guernsey</option><option value="84">Guinea</option><option value="91">Guinea-Bissau</option><option value="92">Guyana</option><option value="96">Haiti</option><option value="94">Honduras</option><option value="93">Hong Kong</option><option value="97">Hungary</option><option value="106">Iceland</option><option value="102">India</option><option value="98" selected="">Indonesia</option><option value="105">Iran</option><option value="104">Iraq</option><option value="99">Ireland</option><option value="101">Isle of Man</option><option value="100">Israel</option><option value="107">Italy</option><option value="109">Jamaica</option><option value="111">Japan</option><option value="108">Jersey</option><option value="110">Jordan</option><option value="122">Kazakhstan</option><option value="112">Kenya</option><option value="115">Kiribati</option><option value="118">Korea (North)</option><option value="119">Korea (South)</option><option value="120">Kuwait</option><option value="113">Kyrgyzstan</option><option value="123">Laos</option><option value="132">Latvia</option><option value="124">Lebanon</option><option value="129">Lesotho</option><option value="128">Liberia</option><option value="133">Libya</option><option value="126">Liechtenstein</option><option value="130">Lithuania</option><option value="131">Luxembourg</option><option value="145">Macau</option><option value="141">Macedonia</option><option value="139">Madagascar</option><option value="153">Malawi</option><option value="155">Malaysia</option><option value="152">Maldives</option><option value="142">Mali</option><option value="150">Malta</option><option value="140">Marshall Islands</option><option value="147">Martinique</option><option value="148">Mauritania</option><option value="151">Mauritius</option><option value="241">Mayotte</option><option value="154">Mexico</option><option value="71">Micronesia</option><option value="136">Moldova</option><option value="135">Monaco</option><option value="144">Mongolia</option><option value="137">Montenegro</option><option value="149">Montserrat</option><option value="134">Morocco</option><option value="156">Mozambique</option><option value="143">Myanmar (Burma)</option><option value="157">Namibia</option><option value="166">Nauru</option><option value="165">Nepal</option><option value="163">Netherlands</option><option value="8">Netherlands Antilles</option><option value="158">New Caledonia</option><option value="168">New Zealand</option><option value="162">Nicaragua</option><option value="159">Niger</option><option value="161">Nigeria</option><option value="167">Niue</option><option value="160">Norfolk Island</option><option value="146">Northern Mariana Islands</option><option value="164">Norway</option><option value="169">Oman</option><option value="175">Pakistan</option><option value="182">Palau</option><option value="180">Palestine</option><option value="170">Panama</option><option value="173">Papua New Guinea</option><option value="183">Paraguay</option><option value="171">Peru</option><option value="174">Philippines</option><option value="178">Pitcairn</option><option value="176">Poland</option><option value="181">Portugal</option><option value="179">Puerto Rico</option><option value="184">Qatar</option><option value="185">Reunion</option><option value="186">Romania</option><option value="188">Russia</option><option value="189">Rwanda</option><option value="12">Samoa (American)</option><option value="239">Samoa (western)</option><option value="201">San Marino</option><option value="205">Sao Tome &amp; Principe</option><option value="190">Saudi Arabia</option><option value="202">Senegal</option><option value="187">Serbia</option><option value="192">Seychelles</option><option value="200">Sierra Leone</option><option value="195">Singapore</option><option value="199">Slovakia</option><option value="197">Slovenia</option><option value="191">Solomon Islands</option><option value="203">Somalia</option><option value="242">South Africa</option><option value="88">South Georgia &amp; the South Sandwich Islands</option><option value="66">Spain</option><option value="127">Sri Lanka</option><option value="27">St Barthelemy</option><option value="196">St Helena</option><option value="117">St Kitts &amp; Nevis</option><option value="125">St Lucia</option><option value="138">St Martin (French part)</option><option value="177">St Pierre &amp; Miquelon</option><option value="232">St Vincent</option><option value="193">Sudan</option><option value="204">Suriname</option><option value="198">Svalbard &amp; Jan Mayen</option><option value="208">Swaziland</option><option value="194">Sweden</option><option value="42">Switzerland</option><option value="207">Syria</option><option value="223">Taiwan</option><option value="214">Tajikistan</option><option value="224">Tanzania</option><option value="213">Thailand</option><option value="212">Togo</option><option value="215">Tokelau</option><option value="219">Tonga</option><option value="221">Trinidad &amp; Tobago</option><option value="218">Tunisia</option><option value="220">Turkey</option><option value="217">Turkmenistan</option><option value="209">Turks &amp; Caicos Is</option><option value="222">Tuvalu</option><option value="226">Uganda</option><option value="225">Ukraine</option><option value="2">United Arab Emirates</option><option value="228">United States</option><option value="229">Uruguay</option><option value="227">US minor outlying islands</option><option value="230">Uzbekistan</option><option value="237">Vanuatu</option><option value="231">Vatican City</option><option value="233">Venezuela</option><option value="236">Vietnam</option><option value="234">Virgin Islands (UK)</option><option value="235">Virgin Islands (US)</option><option value="238">Wallis &amp; Futuna</option><option value="64">Western Sahara</option><option value="245">xx</option><option value="240">Yemen</option><option value="243">Zambia</option><option value="244">Zimbabwe</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Pekerjaan <span class="req">*</span></label>
                    <select name="saksi[${noSaksi}][pekerjaan]" class="select-pekerjaan req-input">
                        <option value="">Pilih Pekerjaan</option>
                        <option value="4">Pegawai BUMN/BUMD</option>
                        <option value="3">Pegawai Negeri Sipil</option>
                        <option value="5">POLRI</option>
                        <option value="1">Tentara Nasional Indonesia</option>
                        <option value="2">Lain-Lain</option>
                    </select>
                    <input type="text" name="saksi[${noSaksi}][pekerjaan_lainnya]" class="input-pekerjaan-lain" placeholder="Isikan Pekerjaan Di Sini" style="display:none; margin-top:8px;">
                </div>
                
                <div class="form-group">
                    <label>Status Kawin <span class="req">*</span></label>
                    <select name="saksi[${noSaksi}][status_kawin]" class="req-input">
                        <option value="">Pilih Status Kawin</option>
                        <option value="1">Kawin</option>
                        <option value="2">Belum Kawin</option>
                        <option value="3">Duda</option>
                        <option value="4">Janda</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Pendidikan <span class="req">*</span></label>
                    <select name="saksi[${noSaksi}][pendidikan]" class="req-input">
                        <option value="">Pilih Pendidikan</option>
                        <option value="12">Belum Sekolah</option><option value="5">D1</option><option value="6">D2</option><option value="7">D3</option><option value="8">D4</option><option value="9">S1</option><option value="10">S2</option><option value="11">S3</option><option value="2">SD</option><option value="4">SLTA</option><option value="3">SLTP</option><option value="0">Tidak Ada</option><option value="1">TK</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Golongan Darah</label>
                    <select name="saksi[${noSaksi}][gol_darah]">
                        <option value="">Pilih Golongan Darah</option>
                        <option value="A">A</option><option value="B">B</option><option value="AB">AB</option><option value="O">O</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Berkebutuhan Khusus</label>
                    <select name="saksi[${noSaksi}][difabel]">
                        <option value="T">Tidak</option>
                        <option value="Y">Ya</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Keterangan</label>
                    <textarea name="saksi[${noSaksi}][keterangan]" rows="3"></textarea>
                </div>
            </div>
        </div>
    `;
    
    $("#listSaksi").append(formHTML);

    // Update urutan visual setiap kali saksi baru ditambah
    updateUrutanSaksi();

    // INISIALISASI SELECT2 DENGAN FITUR AUTOFOCUS
    initSelect2Focus("#prov_" + noSaksi);
    initSelect2Focus("#kab_" + noSaksi);
    initSelect2Focus("#kec_" + noSaksi);
    initSelect2Focus("#kel_" + noSaksi);
    initSelect2Focus("#warga_" + noSaksi);

    // Inisialisasi Datepicker
    $("#tgl_lahir_" + noSaksi).datepicker({
        dateFormat: 'dd/mm/yy',
        changeMonth: true,
        changeYear: true,
        yearRange: "-100:+0",
        onSelect: function(dateText) {
            let umur = hitungUmur(dateText);
            let targetUmur = $(this).closest(".saksi-body").find(".input-umur");
            targetUmur.val(umur);
            
            $(this).css("border", "1px solid #ccc");
            targetUmur.css("border", "1px solid #ccc");
        }
    });

    // Ambil Data Provinsi
    $.get(API_URL + "provinces.json", function(data) {
        let options = '<option value="">- Cari/Pilih Propinsi -</option>';
        data.forEach(function(prov) {
            options += `<option value="${prov.id}">${toTitleCase(prov.name)}</option>`;
        });
        // Update DOM dan update tampilan select2
        $("#prov_" + noSaksi).html(options).trigger('change.select2');
    }).fail(function() {
        console.error("Gagal mengambil data Provinsi.");
    });
}

$(function(){

    // Hapus border merah saat input diketik/dipilih
    $(document).on("input change", ".req-input, .input-pekerjaan-lain", function() {
        if ($(this).val() !== null && $(this).val().toString().trim() !== "") {
            // Khusus elemen select2
            if($(this).hasClass("select2-hidden-accessible")) {
                $(this).next('.select2-container').find('.select2-selection').css("border", "1px solid #ccc");
            } else {
                $(this).css("border", "1px solid #ccc");
            }
        }
    });

    // Judul Dinamis yang dipadukan dengan updateUrutanSaksi
    $(document).on("input", ".input-nama-saksi", function() {
        updateUrutanSaksi();
    });

    // Logika Pekerjaan "Lain-Lain"
    $(document).on("change", ".select-pekerjaan", function() {
        if ($(this).val() === "2") {
            $(this).siblings(".input-pekerjaan-lain").show();
        } else {
            $(this).siblings(".input-pekerjaan-lain").hide().val("").css("border", "1px solid #ccc"); 
        }
    });

    // API WILAYAH: Provinsi -> Kabupaten
    $(document).on("change", ".select-prov", function() {
        let idSaksi = $(this).data("id");
        let idProv = $(this).val();

        $("#kab_" + idSaksi).html('<option value="">- Cari/Pilih Kabupaten/Kota -</option>').trigger('change.select2');
        $("#kec_" + idSaksi).html('<option value="">- Cari/Pilih Kecamatan -</option>').trigger('change.select2');
        $("#kel_" + idSaksi).html('<option value="">- Cari/Pilih Kelurahan/Desa -</option>').trigger('change.select2');

        if (idProv) {
            $.get(API_URL + `regencies/${idProv}.json`, function(data) {
                let options = '<option value="">- Cari/Pilih Kabupaten/Kota -</option>';
                data.forEach(function(kab) {
                    options += `<option value="${kab.id}">${toTitleCase(kab.name)}</option>`;
                });
                $("#kab_" + idSaksi).html(options).trigger('change.select2');
            });
        }
    });

    // API WILAYAH: Kabupaten -> Kecamatan
    $(document).on("change", ".select-kab", function() {
        let idSaksi = $(this).data("id");
        let idKab = $(this).val();

        $("#kec_" + idSaksi).html('<option value="">- Cari/Pilih Kecamatan -</option>').trigger('change.select2');
        $("#kel_" + idSaksi).html('<option value="">- Cari/Pilih Kelurahan/Desa -</option>').trigger('change.select2');

        if (idKab) {
            $.get(API_URL + `districts/${idKab}.json`, function(data) {
                let options = '<option value="">- Cari/Pilih Kecamatan -</option>';
                data.forEach(function(kec) {
                    options += `<option value="${kec.id}">${toTitleCase(kec.name)}</option>`;
                });
                $("#kec_" + idSaksi).html(options).trigger('change.select2');
            });
        }
    });

    // API WILAYAH: Kecamatan -> Kelurahan
    $(document).on("change", ".select-kec", function() {
        let idSaksi = $(this).data("id");
        let idKec = $(this).val();

        $("#kel_" + idSaksi).html('<option value="">- Cari/Pilih Kelurahan/Desa -</option>').trigger('change.select2');

        if (idKec) {
            $.get(API_URL + `villages/${idKec}.json`, function(data) {
                let options = '<option value="">- Cari/Pilih Kelurahan/Desa -</option>';
                data.forEach(function(kel) {
                    options += `<option value="${kel.id}">${toTitleCase(kel.name)}</option>`;
                });
                $("#kel_" + idSaksi).html(options).trigger('change.select2');
            });
        }
    });

    $("#box_pemohon").hide();

    $("#cari").autocomplete({
        source: "index.php",
        minLength: 2,
        select: function(event, ui){

            $("#formBox").show();
            $("#nomor").val(ui.item.value);

            if (ui.item.tipe === "permohonan") {
                $("#box_pemohon").show();
                $("#pemohon").val(ui.item.pemohon);
                $("#box_penggugat").hide();
                $("#box_tergugat").hide();
            } else {
                $("#box_penggugat").show();
                $("#box_tergugat").show();
                $("#penggugat").val(ui.item.penggugat);
                $("#tergugat").val(ui.item.tergugat);
                $("#box_pemohon").hide();

                if (ui.item.tipe === "talak") {
                    $("#label1").text("Pemohon:");
                    $("#label2").text("Termohon:");
                } else {
                    $("#label1").text("Penggugat:");
                    $("#label2").text("Tergugat:");
                }
            }
        }
    });

});
</script>

</body>
</html>