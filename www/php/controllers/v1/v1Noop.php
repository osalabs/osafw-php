<?php
/*
 API Noop controller - sample API controller for testing
*/

#use OpenApi\Attributes as OA;

class v1NoopController extends FwApiController {
    public FwModel $model;
    //    public string $model_name = 'FwModel';
    public string $base_url = '/v1/boop';

    public function __construct() {
        parent::__construct(); #pass false if no auth required
    }

    #sample for Swagger API documentation
    //    #[OA\Get(
    //        path: '/v1/noop',
    //        operationId: 'getNoopStatus',
    //        description: 'Always return true',
    //        summary: 'sample API controller for testing'
    //    )]
    //    #[OA\Response(
    //        response: '200',
    //        description: 'Noop response',
    //        content: new OA\JsonContent(
    //            properties: [
    //                new OA\Property(property: 'boop', type: 'boolean', example: true),
    //            ],
    //            type: 'object'
    //        )
    //    )]
    public function IndexAction(): ?array {
        $ps = [
            'noop' => true,
        ];
        return ['_json' => $ps];
    }

}//end of class
