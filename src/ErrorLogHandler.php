<?php

namespace Arwg\FinalLogger;

use Arwg\FinalLogger\Exceptions\CommonExceptionModel;
use Arwg\FinalLogger\Exceptions\FinalException;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ErrorLogHandler implements ErrorLogHandlerInterface
{

    /* error log trait */
    public function reportErrorLog($exception, $custom_func = null)
    {
        $request = request();

        if (!$exception instanceof FinalException) {
            $this->createFinalException($exception, $custom_func);
        } else {
            $this->processFinalErrorLog($exception->getCode(), $exception->getMessage(), app(GeneralLogHandlerInterface::class)->createGeneralLogText($request));
        }

    }

    public function createFinalException(\Exception $e, $custom_func = null)
    {
        try {

            $internalMessage = $e->getMessage();
            $lowLeverCode = $e->getCode();

            $userCode = config('final-logger.error_user_code')['all unexpected errors'];

            if ($e instanceof FinalException) {
                // Throw this to Handler
                throw $e;
            }

            if($custom_func) {

                $customized_return = call_user_func($custom_func, $e);

                if ($customized_return instanceof FinalException) {
                    // Throw this to Handler
                    throw $customized_return;

                }else{
                    // 500: all unexpected errors
                    throw new FinalException('Your customized function does not return FinalException.',
                        $internalMessage, $userCode, "LowLeverCode : " . $lowLeverCode,
                        config('final-logger.error_code')['Internal Server Error'], $e->getTraceAsString());
                }


            }else {

                // 500: all unexpected errors
                throw new FinalException('Data (server-side) error has occurred. (Recommend creating your own FinalException)',
                    $internalMessage, $userCode, "LowLeverCode : " . $lowLeverCode,
                    config('final-logger.error_code')['Internal Server Error'], $e->getTraceAsString());

            }

        } catch (\Throwable $e2) {

            if (!$e2 instanceof FinalException) {

                throw new FinalException("Failed to create 'FinalException'",
                    $e2->getMessage(), config('final-logger.error_user_code')['all unexpected errors'], "LowLeverCode : " . $e2->getCode(),
                    config('final-logger.error_code')['Internal Server Error'], $e2->getTraceAsString() , $e);
            }else{
                throw $e2;
            }

        }

    }

    // Error Log should include general log as we want to know general data just when the error occurs
    public function processFinalErrorLog(int $final_error_code, string $final_error_message, $generalLogText = null)
    {

        if (!$generalLogText) {
            $generalLogText = app(GeneralLogHandlerInterface::class)->createGeneralLogText(request());
        }

        Log::error('{"final" : {"error_code" : ' . $final_error_code . ', "payload" : ' . $final_error_message . ', "general_log" : ' . $generalLogText . '}}');
    }

}
