<?php

namespace TypechoPlugin\CloudflareR2;

use Widget\Options;
use Typecho\Common;
use Typecho\Widget;
use Typecho\Plugin\Exception;
use Typecho\Widget\Helper\Form;
use Typecho\Plugin\PluginInterface;
use Typecho\Plugin as TypechoPlugin;
use Typecho\Widget\Helper\Form\Element\Text;

/**
 * Cloudflare R2 上传插件
 *
 * @package CloudflareR2
 * @author fengqi
 * @version 0.0.1
 * @link http://fengqi.me
 */
class Plugin implements PluginInterface
{
    public static function activate()
    {
        TypechoPlugin::factory("Widget_Upload")->uploadHandle = [Plugin::class, "uploadHandle"];
        TypechoPlugin::factory("Widget_Upload")->modifyHandle = [Plugin::class, "modifyHandle"];
        TypechoPlugin::factory("Widget_Upload")->deleteHandle = [Plugin::class, "deleteHandle"];
        TypechoPlugin::factory('Widget_Upload')->attachmentHandle = [Plugin::class, 'attachmentHandle'];
        TypechoPlugin::factory('Widget_Upload')->attachmentDataHandle = [Plugin::class, 'attachmentDataHandle'];
    }

    public static function deactivate()
    {
        // TODO: Implement deactivate() method.
    }

    /**
     * 配置面板
     *
     * @param Form $form
     * @return void
     */
    public static function config(Form $form)
    {
        $accountId = new Text('account_id', NULL, NULL, _t('账户id'), _t('当你Cloudflare控制中心点击左侧的“账户首页”时，地址栏那串很长的数字字母混合的就是。'));
        $form->addInput($accountId);

        $bucket = new Text('bucket', NULL, NULL, _t('存储桶'), _t('创建参考：https://developers.cloudflare.com/r2/get-started/#2-create-a-bucket'));
        $form->addInput($bucket);

        $access_key_id = new Text('access_key_id', NULL, NULL, _t('访问密钥ID'), _t('在你创建完成 api token 时会显示。'));
        $form->addInput($access_key_id);

        $secret_access_key = new Text('secret_access_key', NULL, NULL, _t('机密访问密钥'), _t('在你创建完成 api token 时会显示。'));
        $form->addInput($secret_access_key);

        $endpoint = new Text('endpoint', NULL, NULL, _t('Endpoint'), _t('在你创建完成 api token 时会显示，通常是：https://<账户id>.r2.cloudflarestorage.com'));
        $form->addInput($endpoint);

        $domain = new Text('domain', NULL, NULL, _t('公开访问域名'), _t('示例：https://img.example.com，在”存储桶-设置-自定义域“设置。'));
        $form->addInput($domain);
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form
     * @return void
     */
    public static function personalConfig(Form $form)
    {
        // TODO: Implement personalConfig() method.
    }

    /**
     * 获取配置信息
     *
     * @return array
     */
    public static function r2config(): array
    {
        $opts = Widget::widget('Widget_Options')->plugin('CloudflareR2');
        return [
            'account_id' => $opts->account_id,
            'bucket' => $opts->bucket,
            'access_key_id' => $opts->access_key_id,
            'secret_access_key' => $opts->secret_access_key,
            'endpoint' => $opts->endpoint,
            'domain' => $opts->domain,
        ];
    }

    private static function getSignatureKey($key, $date, $regionName, $serviceName): string
    {
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return $kSigning;
    }

    public static function ext($filename, $type = ''): string
    {
        $ext = '';
        $part = explode('.', $filename);
        if (($length = count($part)) > 1) {
            $ext = strtolower($part[$length - 1]);
        }

        // todo 使用mime_type识别

        return $ext;
    }


    /**
     * 上传文件
     *
     * @throws Exception
     */
    public static function uploadHandle($file): array
    {
        // 配置信息
        $config = self::r2config();
        $account_id = $config['account_id'];
        $bucket = $config['bucket'];
        $access_key_id = $config['access_key_id'];
        $secret_access_key = $config['secret_access_key'];
        $endpoint = $config['endpoint'];

        // 机密访问密钥是 Cloudflare R2 api token 的 sha256
        if (strlen($secret_access_key) != 64 && stripos($secret_access_key, '-')) {
            $secret_access_key = hash('sha256', $secret_access_key);
        }

        if (empty($account_id) || empty($access_key_id) || empty($secret_access_key) || empty($bucket) || empty($endpoint)) {
            throw new Exception('missing_credentials, R2 credentials are not set.');
        }

        // 使用md5-16存储文件名
        $tmp_file = $file['file'] ?? $file['tmp_name'];
        $file_path = substr(md5_file($tmp_file), 8, 16) .'_'. $file['name'];
        $content_type = $file['type'];

        // todo 检查远程文件存在

        $file_content = file_get_contents($tmp_file);
        $datetime = gmdate('Ymd\THis\Z');
        $date = substr($datetime, 0, 8);
        $payload_hash = hash('sha256', $file_content);

        $parse = parse_url($config['endpoint']);
        $host = $parse['host'] ?? '';

        // ************* TASK 1: CREATE A CANONICAL REQUEST *************
        $canonical_uri = "/{$bucket}/{$file_path}";
        $canonical_querystring = '';
        $canonical_headers = "content-type:{$content_type}\nhost:{$host}\nx-amz-content-sha256:{$payload_hash}\nx-amz-date:{$datetime}\n";
        $signed_headers = 'content-type;host;x-amz-content-sha256;x-amz-date';
        $canonical_request = "PUT\n{$canonical_uri}\n{$canonical_querystring}\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";

        // ************* TASK 2: CREATE THE STRING TO SIGN *************
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = "{$date}/auto/s3/aws4_request";
        $string_to_sign = "{$algorithm}\n{$datetime}\n{$credential_scope}\n" . hash('sha256', $canonical_request);

        // ************* TASK 3: CALCULATE THE SIGNATURE *************
        $signing_key = self::getSignatureKey($secret_access_key, $date, 'auto', 's3');
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        // ************* TASK 4: ADD SIGNING INFORMATION TO THE REQUEST *************
        $authorization_header = "{$algorithm} Credential={$access_key_id}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";

        $headers = array(
            "Host: {$host}",
            "Content-Type: {$content_type}",
            "x-amz-content-sha256: {$payload_hash}",
            "x-amz-date: {$datetime}",
            "Authorization: {$authorization_header}"
        );

        $api = "{$endpoint}/{$bucket}/{$file_path}";
        $ch = curl_init($api);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $file_content);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code != 200) {
            new Exception('upload_failed, Failed to upload file to R2. HTTP Code: ' . $http_code);
        }

        return [
            'name' => $file_path,
            'path' => "/{$file_path}",
            'size' => $file['size'],
            'type' => self::ext($file['name'], $file['type']),
            'mime' => Common::mimeContentType($tmp_file)
        ];
    }

    /**
     * 修改文件，没啥可改的
     *
     * @param $content
     * @param $file
     * @return array
     */
    public static function modifyHandle($content, $file): array
    {
        return [];
    }

    /**
     * 删除文件
     *
     * @param $content
     * @return bool
     */
    public static function deleteHandle($content): bool
    {
        // todo 调用api删除远程文件
        return true;
    }

    /**
     * 获取文件访问路径
     *
     * @param $content
     * @return string
     */
    public static function attachmentHandle($content): string
    {
        // 以前上传的本地文件
        $root = defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__ ;
        if (file_exists($root . $content['attachment']->path)) {
            $options = Options::alloc();
            $prefix = defined('__TYPECHO_UPLOAD_URL__') ? __TYPECHO_UPLOAD_URL__ : $options->siteUrl;
            return Common::url($content['attachment']->path, $prefix);
        }

        // 远程文件
        $config = self::r2config();
        return $config['domain'] . $content['attachment']->path;
    }

    /**
     * 获取文件内容
     * @param array $content
     * @return string
     */
    public static function attachmentDataHandle(array $content): string
    {
        // todo 下载远程文件
        return '';
    }
}