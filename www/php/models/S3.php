<?php
/*
 S3 storage support

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2025 Oleg Savchuk www.osalabs.com
*/

/*

composer require aws/aws-sdk-php

https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-examples.html

For your AWSAccessKey define permissions like:
YOURBUCKETNAME is same as defined in S3Bucket
you could optionally add /S3Root/* after YOURBUCKETNAME to limit access only to specific root prefix

{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "XXXXXXXXXXXXXXXXXX",
            "Effect": "Allow",
            "Action": [
                "s3:*"
            ],
            "Resource": [
                "arn:aws:s3:::YOURBUCKETNAME",   <-- bucket name only to allow Bucket List queries
                "arn:aws:s3:::YOURBUCKETNAME/*"  <-- note /* here
            ]
        }
    ]
}
 */

use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

class S3 extends FwModel {
    public const bool IS_ENABLED = false;

    public string $region = '';
    public string $bucket = '';
    public string $root = '';
    protected array $credentials = [];

    public S3Client $client;

    public function __construct($param_fw = null) {
        $bucket_config = is_array($param_fw) ? $param_fw : null;

        parent::__construct($bucket_config === null ? $param_fw : null);

        $this->table_name = '';
        $this->initConfig($bucket_config ?? $this->fw->config->AWS);

        $this->initClient(); // automatically init client on start
    }

    protected function initConfig(array $bucket_config): void {
        $this->region = $bucket_config['REGION'] ?? $this->fw->config->AWS['REGION'] ?? '';
        $this->bucket = $bucket_config['BUCKET'] ?? $this->fw->config->AWS['BUCKET'] ?? '';
        $root         = $bucket_config['S3_ROOT'] ?? $this->fw->config->AWS['S3_ROOT'] ?? '';
        $root         = rtrim($root, '/');
        $this->root   = $root === '' ? '' : $root . '/';

        $this->credentials = $bucket_config['CREDENTIALS'] ?? [];
        if (empty($this->credentials['key']) && !empty($bucket_config['ACCESS_KEY']) && !empty($bucket_config['SECRET_KEY'])) {
            $this->credentials = [
                'key'    => $bucket_config['ACCESS_KEY'],
                'secret' => $bucket_config['SECRET_KEY']
            ];
        }
    }

    public static function withBucket(array $bucket_config): S3 {
        return new self($bucket_config);
    }

    public function initClient(): void {
        if (empty($this->region) || empty($this->bucket)) {
            throw new Exception('S3 region/bucket/root is not configured');
        }

        $client_options = [
            'region'  => $this->region,
            'version' => 'latest',
        ];

        if (!empty($this->credentials['key'])) {
            $client_options['credentials'] = $this->credentials;
        }

        $this->client = new S3Client($client_options);
    }

    /**
     * return S3 URL for the key
     * @param string $key
     * @return string
     */
    public function getURL(string $key): string {
        return 'https://s3.' . $this->region . '.amazonaws.com/' . $this->bucket . '/' . $this->root . $key;
    }

    /**
     * check if object actually exists in the bucket
     */
    public function isKeyExists(string $key): bool {
        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->root . $key,
            ]);
            return true;
        } catch (S3Exception $e) {
            if ($e->getStatusCode() == 404) {
                return false;
            }
            logger('NOTICE', 'S3 exists error', $e->getMessage());
            return false;
        } catch (Exception $e) {
            logger('NOTICE', 'S3 exists error', $e->getMessage());
            return false;
        }
    }

    /**
     * create folder relative to S3Root
     * @param string $folder should not contain / at the begin or end
     * @return Result
     */
    public function createFolder(string $folder): Result {
        // remove / from begin and end
        $folder = trim($folder, '/');

        return $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key'    => $this->root . $folder . '/',
            'Body'   => '',
        ]);
    }

    /**
     * return signed url for the key with standard params: 10 min expiration
     * @param string $key
     * @param int $expires
     * @param int $max_age
     * @return string
     *
     * https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-presigned-url.html
     * TODO for cacheing use custom builder which will round current time to 10min (or 1h) and sign with "fixed" time instead current
     * https://stackoverflow.com/questions/45213553/aws-s3-presigned-request-cache
     * or cache signed urls on caller level (Att model)
     */
    public function getSignedUrl(string $key, int $expires = 600, int $max_age = 31536000): string {
        if ($max_age == 0) {
            $max_age = $expires; //special case to match max_age to expires
        }

        $cmd     = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key'    => $this->root . $key,
        ]);
        $request = $this->client->createPresignedRequest($cmd, '+' . $expires . ' seconds');

        //override cache control
        //max age=31536000 with immuatable avoids send revalidation request from browser to resource https://developer.mozilla.org/en-US/docs/Web/HTTP/Caching#avoiding_revalidation
        $request = $request->withHeader('Cache-Control', 'private, max-age=' . $max_age . ', immutable');

        $url = (string)$request->getUri();
        $url .= (str_contains($url, '?') ? '&' : '?') . 'max-age=' . $max_age; // help CDN/browser cache heuristics
        return $url;
    }

    /**
     * delete one object or whole folder
     * @param string $key object key, relative to the S3Root by default
     * @param bool $is_add_root if set to False, the S3Root prefix is not added. Used in recursive folder delete where object key name is obtained as a full path from the S3 API
     * @param bool $is_folder_check if set to False, do not check for the folder "/" ending. Used to delete a folder where the folder itself is an actual object with a zero size
     * @return Result response of one object deletion or response of top folder delete
     * RECURSIVE! for folders
     */
    public function deleteObject(string $key, bool $is_add_root = true, bool $is_folder_check = true): Result {
        logger("deleteObject: [$key] (is_add_root=$is_add_root, is_folder_check=$is_folder_check)");

        if ($is_folder_check && str_ends_with($key, '/')) {
            $prefix            = ($is_add_root ? $this->root : '') . $key;
            $continuationToken = null;

            do {
                $params = [
                    'Bucket'  => $this->bucket,
                    'Prefix'  => $prefix,
                    'MaxKeys' => 1000,
                ];
                if ($continuationToken) {
                    $params['ContinuationToken'] = $continuationToken;
                }

                $list    = $this->client->listObjectsV2($params);
                $objects = [];
                foreach ($list['Contents'] ?? [] as $entry) {
                    if ($entry['Key'] === $prefix) {
                        continue;
                    }
                    $objects[] = ['Key' => $entry['Key']];
                }

                if ($objects) {
                    $this->client->deleteObjects([
                        'Bucket' => $this->bucket,
                        'Delete' => [
                            'Objects' => $objects,
                            'Quiet'   => true,
                        ],
                    ]);
                }

                $continuationToken = (!empty($list['IsTruncated']) && !empty($list['NextContinuationToken']))
                    ? $list['NextContinuationToken']
                    : null;
            } while ($continuationToken);

            return $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $prefix,
            ]);
        }
        return $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key'    => ($is_add_root ? $this->root : '') . $key,
        ]);
    }

    /**
     * upload local file by filepath to the S3
     * @param string $key relative to the S3Root
     * @param string $filepath file path on the local disk to upload
     * @param string $disposition if defined (ex: inline) - Content-Disposition with file.FileName added
     * @param string $filename optional filename to include in disposition header
     * @param array|string|null $options additional options to pass to putObject, or legacy storage class string
     * @return bool
     *
     * alternative way for upload - use Aws\S3\Transfer https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-transfer.html
     */
    public function uploadLocalFile(string $key, string $filepath, string $disposition = '', string $filename = '', array|string|null $options = []): bool {
        logger("uploading to S3: key=[$key], filepath=[$filepath]");
        if (!is_array($options)) {
            $options = ['StorageClass' => $options ?? 'STANDARD'];
        }

        $request                = [
            'Bucket'       => $this->bucket,
            'Key'          => $this->root . $key,
            'SourceFile'   => $filepath,
            'StorageClass' => $options['StorageClass'] ?? 'STANDARD'
        ];
        $request['ContentType'] = UploadUtils::ext2mime(pathinfo($filepath, PATHINFO_EXTENSION));

        if ($options) {
            $request = array_merge($request, $options);
        }

        if ($disposition !== '') {
            if ($filename === '') {
                $filename = basename($filepath);
            }
            $filename                      = str_replace('"', "'", $filename);
            $request['ContentDisposition'] = "$disposition; filename=\"$filename\"";
        }

        $result     = $this->client->putObject($request);
        $meta       = $result['@metadata'] ?? [];
        $statusCode = $meta['statusCode'] ?? null;
        if ($statusCode !== 200) {
            logger('WARN', 'HttpStatusCode', $statusCode, $meta);
        }

        return $statusCode === 200;
    }

    /**
     * upload HttpPostedFile to the S3
     * @param string $key relative to the S3Root
     * @param array $file single file from http upload $_FILES[xxx]
     * @param string $disposition if defined (ex: inline) - Content-Disposition with file.FileName added
     * @param string $filename optional filename to include in disposition header
     * @param array $options additional options to pass to putObject
     * @return Result
     * alternative way for disposition - override response header in GET
     */
    public function uploadPostedFile(string $key, array $file, string $disposition = '', string $filename = '', array $options = []): Result {
        logger("uploading to S3: key=[$key], file name=[{$file['name']}] size=[{$file['size']}] error=[{$file['error']}] ");

        $request                = [
            'Bucket'     => $this->bucket,
            'Key'        => $this->root . $key,
            'SourceFile' => $file['tmp_name'],
        ];
        $request['ContentType'] = UploadUtils::ext2mime($file['type']);

        if ($options) {
            $request = array_merge($request, $options);
        }

        if ($disposition !== '') {
            if ($filename === '') {
                $filename = basename($file['name']);
            }
            $filename                      = str_replace('"', "'", $filename);
            $request['ContentDisposition'] = "$disposition; filename=\"$filename\"";
        }

        return $this->client->putObject($request);
    }

    public function uploadContent(string $key, string $fileContent, string $contentType = 'application/octet-stream', string $disposition = '', string $filename = '', array $more_request = []): Result {
        $request = [
            'Bucket'      => $this->bucket,
            'Key'         => $this->root . $key,
            'Body'        => $fileContent,
            'ContentType' => $contentType,
        ];
        $request = array_merge($request, $more_request);

        if ($disposition !== '') {
            if ($filename === '') {
                $filename = basename($key);
            }
            $filename                      = str_replace('"', "'", $filename);
            $request['ContentDisposition'] = "$disposition; filename=\"$filename\"";
        }

        return $this->client->putObject($request);
    }

    public function uploadFromURL(string $key, string $url, string $disposition = '', string $filename = '', array $more_request = []): Result {
        logger("uploading to S3: key=[$key], url=[$url]");

        $stream = fopen($url, 'rb');
        if ($stream === false) {
            throw new Exception("Failed to read file from URL: " . $url);
        }

        $meta        = stream_get_meta_data($stream);
        $fileContent = stream_get_contents($stream);
        fclose($stream);
        if ($fileContent === false) {
            throw new Exception("Failed to read file from URL: " . $url);
        }

        $contentType      = 'application/octet-stream';
        $response_headers = is_array($meta['wrapper_data'] ?? null) ? $meta['wrapper_data'] : [];
        foreach (array_reverse($response_headers) as $hdr) {
            if (stripos($hdr, 'Content-Type:') === 0) {
                $contentType = trim(substr($hdr, 13));
                break;
            }
        }

        if ($filename === '') {
            $filename = basename($url);
        }

        return $this->uploadContent($key, $fileContent, $contentType, $disposition, $filename, $more_request);
    }

    /**
     * download file from S3 to specific local filepath
     * @param string $key key relative to the S3Root
     * @param string $filepath filepath to download file to
     * @return string downloaded file filepath or empty string if not success
     */
    public function download(string $key, string $filepath): string {
        logger("downloading from S3: key=[$key], filepath=[$filepath]");

        $request = [
            'Bucket' => $this->bucket,
            'Key'    => $this->root . $key,
        ];

        $result     = $this->client->getObject($request);
        $meta       = $result['@metadata'] ?? [];
        $statusCode = $meta['statusCode'] ?? null;
        if ($statusCode !== 200) {
            logger('WARN', 'HttpStatusCode', $statusCode, $meta);
            return '';
        }

        file_put_contents($filepath, $result['Body']);

        return $filepath;
    }

}
