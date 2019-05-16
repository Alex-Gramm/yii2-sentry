<?php
/**
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace gramm\sentry;

use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Severity;
use yii\helpers\ArrayHelper;
use yii\log\Logger;
use yii\log\Target;

/**
 * SentryTarget records log messages in a Sentry.
 *
 * @see https://sentry.io
 */
class SentryTarget extends Target
{
    /**
     * @var string Sentry client key.
     */
    public $dsn;
    /**
     * @var array Options of the ClientBuilder.
     */
    public $clientOptions = [];
    /**
     * @var bool Write the context information. The default implementation will dump user information, system variables, etc.
     */
    public $context = true;

    /**
     * @var bool Write the context information. The default implementation will dump user information, system variables, etc.
     */
    public $stacktrace = true;
    /**
     * @var callable Callback function that can modify extra's array
     */
    public $extraCallback;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @inheritdoc
     */
    public function collect($messages, $final)
    {
        if (!isset($this->client)) {
            $options = array_merge($this->clientOptions,[
                'dsn'=>$this->dsn,
                'attach_stacktrace' => $this->stacktrace,
                'environment' => YII_ENV
            ]);
            $this->client = ClientBuilder::create($options)->getClient();
        }

        parent::collect($messages, $final);
    }

    /**
     * @inheritdoc
     */
    protected function getContextMessage()
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function export()
    {
        foreach ($this->messages as $message) {
            list($text, $level, $category, $timestamp, $traces) = $message;

            if ($text instanceof \Throwable || $text instanceof \Exception) {
                $text = $this->runExtraCallback($text);
                $this->client->captureException($text);
                continue;
            } elseif (is_array($text)) {

                $data = [
                    'level' => static::getLevel($level),
                    'timestamp' => $timestamp,
                    'tags' => ['category' => $category]
                ];

                if (isset($text['msg'])) {
                    $data['message'] = $text['msg'];
                    unset($text['msg']);
                }

                if (isset($text['tags'])) {
                    $data['tags'] = ArrayHelper::merge($data['tags'], $text['tags']);
                    unset($text['tags']);
                }

                $data['extra'] = $text;
            } else {
                $data['message'] = $text;
            }

            if ($this->context) {
                $data['extra']['context'] = parent::getContextMessage();
            }

            $data = $this->runExtraCallback($data);
            $this->client->captureEvent($data);
        }
    }

    /**
     * Calls the extra callback if it exists
     *
     * @param $text
     * @param $data
     * @return array
     */
    public function runExtraCallback($data)
    {
        if (is_callable($this->extraCallback)) {
            $data = call_user_func($this->extraCallback, $data);
        }

        return $data;
    }

    /**
     * Returns the text display of the specified level for the Sentry.
     *
     * @param integer $level The message level, e.g. [[LEVEL_ERROR]], [[LEVEL_WARNING]].
     * @return string
     */
    public static function getLevelName($level)
    {
        static $levels = [
            Logger::LEVEL_ERROR => 'error',
            Logger::LEVEL_WARNING => 'warning',
            Logger::LEVEL_INFO => 'info',
            Logger::LEVEL_TRACE => 'debug',
            Logger::LEVEL_PROFILE_BEGIN => 'debug',
            Logger::LEVEL_PROFILE_END => 'debug',
        ];

        return isset($levels[$level]) ? $levels[$level] : 'error';
    }

    public static function getLevel($level)
    {
        static $levels = [
            Logger::LEVEL_ERROR => 'error',
            Logger::LEVEL_WARNING => 'warning',
            Logger::LEVEL_INFO => 'info',
            Logger::LEVEL_TRACE => 'debug',
            Logger::LEVEL_PROFILE_BEGIN => 'debug',
            Logger::LEVEL_PROFILE_END => 'debug',
        ];

        return new Severity(isset($levels[$level]) ? $levels[$level] : 'error');
    }
}