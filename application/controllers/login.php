<?php
defined('BASEPATH') or exit('No direct script access allowed');

class login extends CI_Controller
{
    private $key = "PKIq1gr6XGSxW6sYdRosM7Qf9gcMQF3tbQcJb11z8qhUBESOzcpy6Z510xaHZDWa";

    public function index()
    {
        if ($this->input->post()) {
            $username = $this->input->post('username');
            $password = $this->input->post('password');
            $usernames = $this->db->get_where('user', ["username" => $username])->row_array();
            if ($usernames && password_verify($password, $usernames['password'])) {
                $this->session->set_userdata('user', $usernames);
                redirect('iso');
            }
        }
        $this->load->view('login');
    }

    public function attact()
    {
        $request = $this->input->get();
        $usr = self::Decode($request['q'], $this->key);
        if (!$usr) {
            show_404();
        }
        $usr = json_decode($usr, true);
        $usedData = [
            "nama" => $usr['name'],
            'username' => $usr['passwords']['username'],
            'password' =>  password_hash($usr['passwords']['password'], PASSWORD_DEFAULT),
            'role' => $usr['roles'][0]['name'] ?? null,
        ];
        // usernameCheck 
        if ($usernames = $this->db->get_where('user', ["username" => $usr['passwords']['username']])->row_array()) {
            // login
            $this->session->set_userdata('user', $usernames);
            redirect('iso');
        } else {
            $this->db->insert('user', $usedData);
            // login
            $this->session->set_userdata('user', $usedData);
            redirect('iso');
        }
    }



    /**
     * Encode - Encrypt string
     * 
     * @param string $input - String to encode
     * @param string $key - Secret key
     * @param string $enc_method (optional)
     * 
     * @return string Encrypted string
     */
    static function Encode(string $input, string $key, string $enc_method = 'AES-256-CBC')
    {
        $enc_iv = self::_generateIV($key, openssl_cipher_iv_length($enc_method));

        return base64_encode(openssl_encrypt($input, $enc_method, $key, 0, $enc_iv));
    }

    /**
     * Decode - Decrypt encrypted string
     * 
     * @param string $input - Encrypted string
     * @param string $key - Secret key
     * @param string $enc_method (optional)
     * 
     * @return string Decoded string
     */
    static function Decode(string $input, string $key, string $enc_method = 'AES-256-CBC')
    {
        $enc_iv = self::_generateIV($key, openssl_cipher_iv_length($enc_method));

        return openssl_decrypt(base64_decode($input), $enc_method, $key, 0, $enc_iv);
    }

    /**
     * _generateIV - Automatic generate rquired encrypion iv from key
     * 
     * @param string $key - Secret key
     * @param int $size - Size of the iv
     * @return string iv
     */
    static function _generateIV($key, $size)
    {
        $hash = base64_encode(md5($key));
        while (strlen($hash) < $size) {
            $hash = $hash . $hash;
        }
        return substr($hash, 0, $size);
    }
}
