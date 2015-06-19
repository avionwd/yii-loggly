<?php
/**
 * LogglyRouter file
 * @copyright &copy; Iterios
 * @author Oleksandr Muzychenko <almuz@iterios.com>
 */

/**
 * 
 * @package 
 * @version 1.0 2015-06-19
 */

class LogglyRouter extends CLogRoute
{
    /**
     * The predefine url for service
     * @var string
     */
    public $url = 'http://logs-01.loggly.com/bulk/$TOKEN$/tag/$TAGS$/';

    /**
     * Access token for loggly service
     * @var string
     */
    public $token;

    /**
     * Comma separated tags or array with tags
     * @example 'tag1,tag2'
     * @var string|array
     */
    public $tags;

    /**
     * Include environment tag
     * @var bool
     */
    public $tagEnvironment = true;

    /**
     * Initialize
     */
    public function init()
    {
        parent::init();

        if (is_string($this->tags) && strlen($this->tags) > 0) {
            $this->tags = explode(',', $this->tags);
        }
        if (is_array($this->tags)) {
            array_walk($this->tags, function(&$item) {
                $item = trim($item);
            });
        }
        if ($this->tagEnvironment && $env = getenv('APP_ENV')) {
            settype($this->tags, 'array');
            array_unshift($this->tags, $env);
        }
    }

    /**
     * @return mixed
     */
    protected function getUrl()
    {
        $url = str_replace('$TOKEN$', $this->token, $this->url);
        if ($this->tags) {
            $url = str_replace('$TAGS$', implode(',', $this->tags), $url);
        }
        return $url;
    }

    /**
     * Saves log messages in files.
     * @param array $logs list of log messages
     * @throws CException
     */
    protected function processLogs($logs)
    {
        $log_items = [];
        foreach($logs as $log) {
            array_push($log_items, $this->formatLogMessage($log[0],$log[1],$log[2],$log[3]));
        }

        if (!$log_items) {
            return;
        }

        $ch = curl_init($this->getUrl());
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, implode("\n", $log_items));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        if ($result = curl_exec($ch)) {
            $data = json_decode($result, true);
            if ($data['response'] != 'ok') {
                throw new CException(__CLASS__ . ' error response ' . $result);
            }
        }
    }

    /**
     * Formats a log message given different fields.
     * @param string $message message content
     * @param integer $level message level
     * @param string $category message category
     * @param integer $time timestamp
     * @return string formatted message
     */
    protected function formatLogMessage($message, $level, $category, $time)
    {
        return json_encode([
            'timestamp' => date('c', $time),
            'category'  => $category,
            'level'     => $level,
            'message'   => $message
        ]);
    }
}