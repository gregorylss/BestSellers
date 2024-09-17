<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BestSellers\Controller;

use BestSellers\BestSellers;
use BestSellers\Form\Configuration;
use ClassicRide\ClassicRide;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;

class ConfigController extends BaseAdminController
{
    public function setAction()
    {
        $form = $this->createForm(Configuration::getName());

        try {
            $configForm = $this->validateForm($form);
            $orderData = $configForm->get('order')->getData();
            $formData = $configForm->all();
            $startDateForm = $formData['start_date'];
            $endDateForm = $formData['end_date'];
            $dateRangeForm = $formData['date_range'];
            $dateType = $formData['date_type'];

            $date_range = $dateRangeForm->getData();

            if ($startDate = $startDateForm->getData()) {
                $startDate = $startDate->format('Y-m-d');
            }
            if ($endDate = $endDateForm->getData()) {
                $endDate = $endDate->format('Y-m-d');
            }

            $dateType = $dateType->getData();

            if ($startDate !== null) {
                BestSellers::setConfigValue(
                    'start_date',
                    $startDate,
                );
            }

            if ($endDate !== null) {
                BestSellers::setConfigValue(
                    'end_date',
                    $endDate,
                );
            }

            if ($orderData !== null) {
                BestSellers::setConfigValue(
                    'order_types',
                    $orderData,
                );
            }

            if ($date_range !== null) {
                BestSellers::setConfigValue(
                    'date_range',
                    $date_range,
                );
            }

            if ($dateType !== null) {
                BestSellers::setConfigValue(
                    'date_type',
                    $dateType,
                );
            }

            $response = $this->redirectAction();

        } catch (FormValidationException $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans(
                    'Error',
                    [],
                ),
                $e->getMessage(),
                $form
            );

            return $this->generateSuccessRedirect($form);
        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans(
                    'Error',
                    [],
                ),
                $e->getMessage(),
                $form
            );
            return $this->generateSuccessRedirect($form);
        }

        return $response;
    }
    public function redirectAction()
    {
        return $this->generateRedirectFromRoute('admin.module.configure', [], ['module_code' => 'BestSellers']);
    }
}
