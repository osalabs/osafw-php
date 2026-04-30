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
use Aws\S3\S3Client;

class S3 extends FwModel {
    public const bool IS_ENABLED = false;

    public string $region = '';
    public string $bucket = '';
    public string $root = '';

    public S3Client $client;

    public function __construct() {
        parent::__construct();

        $this->table_name = '';

        $this->initClient(); // automatically init client on start
    }

    public function initClient(): void {
        $this->bucket = $this->fw->config->AWS['BUCKET'];
        $this->root   = $this->fw->config->AWS['S3_ROOT'];

        //throw exception if region/bucket/akey/skey is not defined
        if (empty($this->fw->config->AWS['ACCESS_KEY'])
            || empty($this->fw->config->AWS['SECRET_KEY'])
            || empty($this->region)
            || empty($this->bucket)
            || empty($this->root)
        ) {
            throw new Exception('S3 region/bucket/root is not configured');
        }

        $this->client = new S3Client([
            'region'      => $this->region,
            'version'     => 'latest',
            'credentials' => [
                'key'    => $this->fw->config->AWS['ACCESS_KEY'],
                'secret' => $this->fw->config->AWS['SECRET_KEY']
            ],
        ]);
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
        $url .= '?max-age=' . $max_age;
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
            // it's subfolder - delete all content first
            $list = $this->client->listObjectsV2([
                'Bucket'    => $this->bucket,
                'Prefix'    => ($is_add_root ? $this->root : '') . $key,
                'Delimiter' => '/',
            ]);

            //delete objects in folder first. Note: object can be a folder itself with a zero size if
            // it was created separately with no body and key name ending with "/",
            // so set "is_folder_check" to False here to delete an object and avoid an infinite loop
            foreach ($list['Contents'] as $entry) {
                $this->deleteObject($entry['Key'], false, false);
            }

            //delete subfolders if any
            foreach ($list['CommonPrefixes'] as $subfolder) {
                $this->deleteObject($subfolder['Prefix'], false);
            }
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
     * @param string|null $storage_class S3 Storage Class, default is STANDARD or use 5 times cheaper GLACIER_IR
     * @return bool
     *
     * alternative way for upload - use Aws\S3\Transfer https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-transfer.html
     */
    public function uploadLocalFile(string $key, string $filepath, string $disposition = '', string $filename = '', string $storage_class = null): bool {
        logger("uploading to S3: key=[$key], filepath=[$filepath]");

        $request                = [
            'Bucket'       => $this->bucket,
            'Key'          => $this->root . $key,
            'SourceFile'   => $filepath,
            'StorageClass' => $storage_class ?? 'STANDARD'
        ];
        $request['ContentType'] = UploadUtils::ext2mime(pathinfo($filepath, PATHINFO_EXTENSION));

        if ($disposition != '') {
            if ($filename == '') {
                $filename = basename($filepath);
            }
            $filename                      = str_replace('"', "'", $filename); // replace quotes
            $request['ContentDisposition'] = "$disposition; filename=\"$filename\"";
        }

        $result = $this->client->putObject($request);
        if ($result['@metadata']['statusCode'] != 200) {
            logger('WARN', "HttpStatusCode=", $result['@metadata']['statusCode']);
        }

        return ($result['@metadata']['statusCode'] == 200);
    }

    /**
     * upload HttpPostedFile to the S3
     * @param string $key relative to the S3Root
     * @param array $file single file from http upload $_FILES[xxx]
     * @param string $disposition if defined (ex: inline) - Content-Disposition with file.FileName added
     * @param string $filename optional filename to include in disposition header
     * @return Result
     * alternative way for disposition - override response header in GET
     */
    public function uploadPostedFile(string $key, array $file, string $disposition = '', string $filename = ''): Result {
        logger("uploading to S3: key=[$key], file=[$file]");

        $request                = [
            'Bucket'     => $this->bucket,
            'Key'        => $this->root . $key,
            'SourceFile' => $file['tmp_name'],
        ];
        $request['ContentType'] = UploadUtils::ext2mime($file['type']);

        if ($disposition != '') {
            if ($filename == '') {
                $filename = basename($file['name']);
            }
            $filename                      = str_replace('"', "'", $filename); // replace quotes
            $request['ContentDisposition'] = "$disposition; filename=\"$filename\"";
        }

        return $this->client->putObject($request);
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

        $result = $this->client->getObject($request);
        if ($result['@metadata']['statusCode'] != 200) {
            logger('WARN', "HttpStatusCode=", $result['@metadata']['statusCode']);
            return '';
        }

        file_put_contents($filepath, $result['Body']);

        return $filepath;
    }

}
