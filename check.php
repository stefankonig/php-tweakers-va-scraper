<?php

/**
 *
 * This a quick 'n dirty scraper to keep watch on tweakers.net vraag & aanbod
 *
 * @author Stefan Konig <github@seosepa.net>
 *
 * Class tweakChecker
 */
class tweakChecker
{
    /**
     * include the category for specific notifications
     */
    private $tweakersUrl = "https://tweakers.net/categorie/714/servers/aanbod/";

    /**
     * Be on the lookout for certain keywords in the title, and e.g. give it a high prio pushover
     */
    private $bolo = [
        'DL180'
    ];

    private $pushoverToken = "";
    private $pushoverUser = "";

    /**
     * STDOUT simple debuglogging
     */
    private $debug = true;

    /**
     * no need for this to change
     */
    private $cookieJarPath = "/tmp/tweakers-cookie.jar";
    private $statefile = "/tmp/tweakers-statefile.tmp";


    /**
     * Run this to do a check
     */
    public function run()
    {
        $output         = $this->curlTweakers();
        $returnTo       = '';
        $tweakers_token = '';

        if (preg_match("/value=\"(.*\|" . preg_quote($this->tweakersUrl, "/") . ")\"/U", $output, $matches)) {
            $returnTo = $matches[1];
            if (preg_match("/name=\"tweakers_token\" value=\"(.*)\"/U", $output, $matches)) {
                $tweakers_token = $matches[1];
            }
        }

        $this->debugLog("returnTo: {$returnTo}");
        $this->debugLog("tweakersToken: {$tweakers_token}");

        $output2 = $this->curlTweakers(["returnTo={$returnTo}", "tweakers_token={$tweakers_token}"]);

        $ads = $this->processPage($output2);
        $this->debugLog("Parsed " . count($ads) . " VA ads");
        $newAds = $this->checkChangesAndSaveState($ads);
        $this->debugLog(count($newAds) . " new ads");

        $this->notifyNewAds($newAds);
    }

    /**
     * @param array $postVars
     * @return array
     */
    public function curlTweakers($postVars = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->tweakersUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJarPath);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJarPath);

        if (count($postVars) > 0) {
            $poststring = implode("&", $postVars);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $poststring);
        }

        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    /**
     * some awful parsing, but gets the job done
     *
     * @param string $rawHtml
     * @return array
     */
    public function processPage($rawHtml)
    {
        $ads = [];

        // split it up into the small peaces where interested in
        $html = explode("</tr><tr>", $rawHtml);

        foreach ($html as $rawAd) {
            if (preg_match(
                "~href=\"(https://tweakers.net/aanbod/\S+)\" title=\"(.*)\".*<span class=\"date\">" .
                ".*</span> - (.*)\s+</p>.*rel=\"nofollow\">\s+(.*)\s+</a>\s+</p>\s+</td>~U",
                str_replace(PHP_EOL, '', $rawAd),
                $matches
            )) {

                $price = str_replace('&euro; ', '', $matches[4]);
                $price = trim($price);
                $price = str_replace(',-', ' EU', $price);

                $ads[] = [
                    'title'       => html_entity_decode($matches[2]),
                    'description' => html_entity_decode($matches[3]),
                    'price'       => $price,
                    'url'         => $matches[1],
                ];
            } else {
                $this->debugLog($rawAd);
            }
        }

        return $ads;
    }

    /**
     * @param array $newAds
     * @return array
     */
    public function checkChangesAndSaveState($newAds)
    {
        $laststate = [];
        $newState  = [];
        $addedAds  = [];

        if (file_exists($this->statefile)) {
            $laststate = file_get_contents($this->statefile);
            if (json_decode($laststate)) {
                $laststate = json_decode($laststate, true);
            }
        }

        foreach ($newAds as $newAd) {
            $newAdTitle = $newAd['title'];
            $newState[] = $newAdTitle;

            if (!in_array($newAdTitle, $laststate)) {
                $addedAds[] = $newAd;
            }
        }

        file_put_contents($this->statefile, json_encode($newState));

        if (count($laststate) > 0) {
            return $addedAds;
        }

        return [];
    }

    /**
     * @param array $newAds
     */
    public function notifyNewAds($newAds)
    {
        foreach ($newAds as $newAd) {
            $price       = $newAd['price'];
            $title       = $newAd['title'];
            $description = $newAd['description'];
            $url         = $newAd['url'];

            $this->pushover("{$price} - {$title}", "{$description} - {$url}");
        }
    }

    /**
     * @param string $title
     * @param string $message
     */
    public function pushover($title, $message)
    {
        $this->debugLog("PUSHOVER: {$title} - {$message}");
        $priority = 0;

        // check for items to "be on look out" for and give higher prio
        foreach ($this->bolo as $bolo) {
            if (stripos($title, $bolo)) {
                $this->debugLog("PUSHOVER: matched bolo {$bolo} to title");
                $priority = 1;
            }
        }

        curl_setopt_array(
            $ch = curl_init(),
            array(
                CURLOPT_URL        => "https://api.pushover.net/1/messages.json",
                CURLOPT_POSTFIELDS => array(
                    "token"     => $this->pushoverToken,
                    "user"      => $this->pushoverUser,
                    "title"     => "{$title}",
                    "message"   => $message,
                    "priority"  => $priority,
                    "timestamp" => time(),
                )
            )
        );
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * @param string $message
     */
    private function debugLog($message)
    {
        if ($this->debug) {
            echo date('H:i:s') . " - " . $message . PHP_EOL;
        }
    }
}

$tweakers = new tweakChecker();
$tweakers->run();
