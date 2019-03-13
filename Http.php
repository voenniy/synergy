<?php


class Http
{
    protected $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    /**
     * @param array $data
     * @return bool|string
     * @throws Exception
     */
    public function post(array  $data)
    {
        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, $this->url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
            $out = curl_exec($curl);
            curl_close($curl);
        } else {
            throw new Exception("Не удалось инициализировать Curl");
        }

        return $out;
    }
}
