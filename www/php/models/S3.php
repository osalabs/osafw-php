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

class S3 extends FwModel {
    public const bool IS_ENABLED = false;

    public string $region = '';
    public string $bucket = '';
    public string $root = '';

    public AmazonS3Client $client;

    public function __construct() {
        parent::__construct();

        $this->table_name = '';

        $this->initClient(); // automatically init client on start
    }

    //createFolder
    //getSignedUrl
    //deleteObject
    //uploadLocalFile
    //uploadPostedFile
    //download

}
