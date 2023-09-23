<?php

use FOSSBilling\InjectionAwareInterface;
use Pimple\Container;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\BadRequestError;
use Razorpay\Api\Errors\Error;
use Razorpay\Api\Errors\GatewayError;
use Razorpay\Api\Errors\ServerError;
use Razorpay\Api\Errors\SignatureVerificationError;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    // Development environment
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    // Build environment
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Handle the case when the autoloader is not found
    die('Autoloader not found.');
}


/**
 * Razorpay Boxbilling Integration.
 *
 * @property mixed $apiId
 * @author Albin Varghese
 */
class Payment_Adapter_Razorpay implements InjectionAwareInterface
{
    protected $di;
    private $apiId;
    private Api $api;
    private array $config;

    public function __construct($config)
    {
        $this->config = $config;

        if (!isset($this->config['key_id'])) {
            throw new Payment_Exception('Payment gateway "Razorpay" is not configured properly. Please update configuration parameter "key_id" at "Configuration -> Payments".');
        }

        if (!isset($this->config['secret_key'])) {
            throw new Payment_Exception('Payment gateway "Razorpay" is not configured properly. Please update configuration parameter "secret_key" at "Configuration -> Payments".');
        }

        // get keys based on the environment.
        $this->apiId = ($this->config['test_mode'] === 0) ? $this->get_key_id() : $this->get_test_key_id();
        $apiSecret = ($this->config['test_mode'] === 0) ? $this->get_secret_key() : $this->get_test_secret_key();

        // Create a new instance for RazorPay SDK.
        $this->api = new Api($this->apiId, $apiSecret);

        // Check if the RazorPay authentication is successful.

    }

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'description' => ' You authenticate to the Razorpay API by providing one of your API keys in the request. You can manage your API keys from your account.',
            'logo' => [
                'logo' => 'Razorpay.png',
                'height' => '50px',
                'width' => '50px',
            ],
            'form' => [
                'key_id' => [
                    'text', [
                        'label' => 'Key Id:',
                        'required' => false,
                    ],
                ],
                'secret_key' => [
                    'text', [
                        'label' => 'Key Secret:',
                        'required' => false,
                    ],
                ],
                'test_key_id' => [
                    'text', [
                        'label' => 'Test Key Id:',
                        'required' => false,
                    ],
                ],
                'test_secret_key' => [
                    'text', [
                        'label' => 'Test Key Secret:',
                        'required' => false,
                    ],
                ],
            ],
        ];
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);

        return $this->_generateForm($invoiceModel);
    }

    public function getAmountInCents(Model_Invoice $invoice)
    {
        $invoiceService = $this->di['mod_service']('Invoice');
        return $invoiceService->getTotalWithTax($invoice) * 100;
    }

    public function getInvoiceTitle(Model_Invoice $invoice)
    {
        $invoiceItems = $this->di['db']->getAll('SELECT title from invoice_item WHERE invoice_id = :invoice_id', [':invoice_id' => $invoice->id]);

        $params = [
            ':id' => sprintf('%05s', $invoice->nr),
            ':serie' => $invoice->serie,
            ':title' => $invoiceItems[0]['title'], ];
        $title = __('Payment for invoice :serie:id [:title]', $params);
        if (count($invoiceItems) > 1) {
            $title = __('Payment for invoice :serie:id', $params);
        }

        return $title;
    }

    public function logError(Exception $e, Model_Transaction $tx)
    {
        $body = $e->getJsonBody();
        $err = $body['error'];
        $tx->txn_status = $err['type'];
        $tx->error = $err['message'];
        $tx->status = 'processed';
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);

        if ($this->di['config']['debug']) {
            error_log(json_encode($e->getJsonBody()));
        }

        throw new Exception($tx->error);
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        $ipn = $data['post'];

        // read fields required to verify the payment from ipn.
        $paymentId = $ipn["razorpay_payment_id"];
        $signature = $ipn["razorpay_signature"];

        // get the current invoice instance.
        $invoice = $this->di['db']->getExistingModelById('Invoice', $data['get']['bb_invoice_id']);
        $tx = $this->di['db']->getExistingModelById('Transaction', $id);

        $invoiceAmountInCents = $this->getAmountInCents($invoice);
        $existingOrderSession = "{$invoice->buyer_email}_{$invoice->serie}_{$invoice->nr}";

        $title = $this->getInvoiceTitle($invoice);

        $success = false;
        $error = "Payment Failed";
        if (empty($ipn['razorpay_payment_id']) === false) {
            try {
                $attributes = [
                    'razorpay_order_id' => $data['get']['rzp_order_id'],
                    'razorpay_payment_id' => $ipn['razorpay_payment_id'],
                    'razorpay_signature' => $ipn['razorpay_signature']
                ];

                $this->api->utility->verifyPaymentSignature($attributes);
                $success =  true;
            } catch(SignatureVerificationError $e) {
                $error = 'Razorpay Error : ' . $e->getMessage();
            }
        }

        if ($success === true) {
            $tx->invoice_id = $invoice->id;

            try {
                $charge = $this->api->payment->fetch($paymentId);
                $tx->txn_status = $charge->status;
                $tx->txn_id = $charge->id;
                $tx->type = $charge->method;
                $tx->amount = $charge->amount / 100;
                $tx->currency = $charge->currency;

                $bd = [
                    'amount' => $tx->amount,
                    'description' => 'Razorpay transaction '.$tx->txn_id,
                    'type' => 'transaction',
                    'rel_id' => $tx->id,
                ];

                $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
                $clientService = $this->di['mod_service']('client');
                $clientService->addFunds($client, $bd['amount'], $bd['description'], $bd);

                $invoiceService = $this->di['mod_service']('Invoice');
                if ($tx->invoice_id) {
                    $invoiceService->payInvoiceWithCredits($invoice);
                }

                $invoiceService->doBatchPayWithCredits(['client_id' => $client->id]);

                //unset existing order_id stored in session.
                unset($_SESSION[$existingOrderSession]);

            } catch (ServerError|SignatureVerificationError|BadRequestError|GatewayError|Error $e) {
                $this->logError($e, $tx);
            }

            $tx->status = 'processed';
            $tx->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($tx);
        } else {
            var_dump($e->getMessage());
            exit();
            $this->logError($e, $tx);
        }
    }

    public function get_test_key_id()
    {
        if (!isset($this->config['test_key_id'])) {
            throw new Payment_Exception('Payment gateway "Razorpay" is not configured properly. Please update configuration parameter "test_key_id" at "Configuration -> Payments".');
        }

        return $this->config['test_key_id'];
    }

    public function get_test_secret_key()
    {
        if (!isset($this->config['test_secret_key'])) {
            throw new Payment_Exception('Payment gateway "Razorpay" is not configured properly. Please update configuration parameter "test_api_secret" at "Configuration -> Payments".');
        }

        return $this->config['test_secret_key'];
    }

    public function get_key_id()
    {
        if (!isset($this->config['key_id'])) {
            throw new Payment_Exception('Payment gateway "Razorpay" is not configured properly. Please update configuration parameter "test_key_id" at "Configuration -> Payments".');
        }

        return $this->config['key_id'];
    }

    public function get_secret_key()
    {
        if (!isset($this->config['secret_key'])) {
            throw new Payment_Exception('Payment gateway "Razorpay" is not configured properly. Please update configuration parameter "test_api_secret" at "Configuration -> Payments".');
        }

        return $this->config['secret_key'];
    }

    /**
     * @param Model_Invoice $invoice
     * @return string
     */
    protected function _generateForm(Model_Invoice $invoice)
    {
        $dataAmount = $this->getAmountInCents($invoice);
        $settingService = $this->di['mod_service']('System');
        $company = $settingService->getCompany();
        $title = $this->getInvoiceTitle($invoice);

        // Razorpay requires an order to be created before any payments.
        // This may lead to the creation of multiple orders on the RazorPay platform.
        // To minimise this, the order created for each invoice is regulated by validating the session.
        $existingOrderSession = "{$invoice->buyer_email}_{$invoice->serie}_{$invoice->nr}";
        //unset existing order_id stored in session.


        if(!isset($_SESSION[$existingOrderSession])) {
            $res = $this->api->order->create(
                [
                    'receipt' => $invoice->serie . sprintf("%05d", $invoice->nr),
                    'amount' => $dataAmount,
                    'currency' => $invoice->currency,
                    'notes' => [
                        'title' => $title,
                    ]
                ]
            );
            $orderId = $res->id ?? null;
            $_SESSION[$existingOrderSession] = $orderId;
        } else {
            $orderId = $_SESSION[$existingOrderSession];
            // Unnecessary, Just to make sure that the order exists.
            $res = $this->api->order->fetch($orderId);
            $orderId = $res->id;
        }

        $form = '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                    <button id="rzp-button" class="btn btn-primary">Pay with Razorpay</button>
                    <form name="razorpayform" action=":callbackUrl" method="POST"  >
                        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
                        <input type="hidden" name="razorpay_signature"  id="razorpay_signature" >
                    </form>
                  <script>
                    var options = :options
                    options.handler = function (response){
                        document.getElementById("razorpay_payment_id").value = response.razorpay_payment_id;
                        document.getElementById("razorpay_signature").value = response.razorpay_signature;
                        document.razorpayform.submit();
                    };
                    
                    var rzp = new Razorpay(options);
                    document.getElementById("rzp-button").onclick = function(e){
                        rzp.open();
                        e.preventDefault();
                    }
         </script>';

        $optionsArray = [
            "key"               => $this->apiId,
            "amount"            => $dataAmount,
            "name"              => $company['name'],
            "image"             => $company['logo_url'],
            "order_id"          => $orderId,
            "description"       => $existingOrderSession,
        ];

        $options = json_encode($optionsArray);

        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Razorpay"');
        $bindings = [
            ':key' => $this->apiId,
            ':amount' => $dataAmount,
            ':currency' => $invoice->currency,
            ':name' => $company['name'],
            ':description' => $title,
            ':image' => $company['logo_url'],
            ':email' => $invoice->buyer_email,
            ':order_id' => $orderId,
            ':label' => __('Pay now'),
            ':options' => $options,
            ':callbackUrl' => $this->getCallbackUrl($payGateway, $invoice, $orderId),
        ];

        return strtr($form, $bindings);
    }


    public function getCallbackUrl(Model_PayGateway $pg, $model = null, $orderId = null)
    {
        $p = [
            'bb_gateway_id' => $pg->id,
            'rzp_order_id' => $orderId,
        ];
        if ($model instanceof Model_Invoice) {
            $p['bb_invoice_id'] = $model->id;
            $p['bb_invoice_hash'] = $model->hash;
            $p['bb_redirect'] = 1;
        }
        return $this->di['config']['url'].'bb-ipn.php?'.http_build_query($p);
    }
}
