<?php
/**
 * Description of authority
 *
 * @author Sebastian Netuschil
 */

class AmazonAPI
{

    /*
     * Public Parameter
     */
    public $Artist;
    public $Title;
    public $Keywords;
    public $MusicLabel;
    public $Orchestra;
    public $Publisher;
    public $MaximumPrice;
    public $MinimumPrice;

    /*
     * Private Parameter
     */
    private $AWSAccessKeyID;
    private $AWSSecretAccessKey;
    private $region = "de";
    private $typ;
    private $errMsg = array();

    public function __construct($region, $AWSAccessKeyID, $AWSSecretAccessKey)
    {
        $this->AWSAccessKeyID = $AWSAccessKeyID;
        $this->AWSSecretAccessKey = $AWSSecretAccessKey;
        $this->region = $region;
    }

    public function error()
    {
        return $this->errMsg;
    }

    private function getRequest($canonicalized_query)
    {
        $method = "GET";
        $host = "ecs.amazonaws." . $this->region;
        $uri = "/onca/xml";

        $string_to_sign = $method . "\n" . $host . "\n" . $uri . "\n" . $canonicalized_query;
        $signature = base64_encode(hash_hmac("sha256", $string_to_sign, $this->AWSSecretAccessKey, True));
        $signature = str_replace("%7E", "~", rawurlencode($signature));

        $request = "http://" . $host . $uri . "?" . $canonicalized_query . "&Signature=" . $signature;

        return $request;
    }

    private function getAmazonXML()
    {
        $settings = $this->creatSettings();
        $settings["Service"] = "AWSECommerceService";
        $settings["AWSAccessKeyId"] = $this->AWSAccessKeyID;
        $settings["Timestamp"] = gmdate("Y-m-d\TH:i:s\Z");
        $settings["Version"] = "2009-03-31";
        $settings["AssociateTag"] = "radihitw-21";
        ksort($settings);

        $canonicalized_query = array();
        foreach ($settings as $param => $value) {
            $param = str_replace("%7E", "~", rawurlencode($param));
            $value = str_replace("%7E", "~", rawurlencode($value));
            $canonicalized_query[] = $param . "=" . $value;
        }
        $canonicalized_query = implode("&", $canonicalized_query);

        $response = file_get_contents($this->getRequest($canonicalized_query));

        if ($response === False) {
            $errMsg[] = "No connection to Amazon Api";
            return False;
        } else {
            $pxml = simplexml_load_string($response);
            if ($pxml === False) {
                $errMsg[] = "Amazon XML file can not be parsed";
                return False;
            } else {
                return $pxml;
            }
        }
    }

    private function creatSettings()
    {
        $settings = array();

        $settings["SearchIndex"] = $this->typ;
        $settings["Operation"] = "ItemSearch";
        $settings["ResponseGroup"] = "Large";

        if ($this->Artist)
            $settings["Artist"] = $this->Artist;

        if ($this->Keywords)
            $settings["Keywords"] = $this->Keywords;

        if ($this->MusicLabel)
            $settings["MusicLabel"] = $this->MusicLabel;

        if ($this->Orchestra)
            $settings["Orchestra"] = $this->Orchestra;

        if ($this->Publisher)
            $settings["Publisher"] = $this->Publisher;

        if ($this->Titel)
            $settings["Titel"] = $this->Titel;

        return $settings;
    }

    private function getMusic()
    {

        $items = array();
        $pxml = $this->getAmazonXML();

        foreach ($pxml->Items->Item as $item) {
            $music = new Music();
            $music->Title = $item->ItemAttributes->Title;
            $music->Artist = $item->ItemAttributes->Artist;
            $music->MediumImage = array("URL" => $item->MediumImage->URL,
                "Height" => $item->MediumImage->Height,
                "Width" => $item->MediumImage->Width
            );
            $music->ASIN = $item->ASIN;
            $items[] = $music;
        }

        return $items;
    }

    public function search($typ)
    {
        switch ($typ) {
            case "Music" :
                $this->typ = $typ;
                return $this->getMusic();
                break;
        }
    }
}

class Music
{

    public $Title;
    public $Artist;
    public $MusicLabel;
    public $Publisher;
    public $Preis;
    public $ASIN;
    public $AudioFormat;
    public $Genre;
    public $Amount;
    public $LargeImage;
    public $MediumImage;
    public $SmallImage;
}

?>
