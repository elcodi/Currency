<?php

/**
 * This file is part of the Elcodi package.
 *
 * Copyright (c) 2014 Elcodi.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author Marc Morera <yuhu@mmoreram.com>
 * @author Aldo Chiecchia <zimage@tiscali.it>
 */

namespace Elcodi\Component\Currency\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Elcodi\Component\Currency\Entity\Interfaces\CurrencyExchangeRateInterface;

/**
 * Class LoadExchangeRatesCommand
 *
 * @package Elcodi\Component\Currency\Command
 */
class LoadExchangeRatesCommand extends ContainerAwareCommand
{
    /**
     * configure
     */
    protected function configure()
    {
        $this
            ->setName('elcodi:currency:loadexchangerates')
            ->setDescription('Loads exchange rates');
    }

    /**
     * This command loads all the exchange rates from base_currency to all available
     * currencies
     *
     * @param InputInterface  $input  The input interface
     * @param OutputInterface $output The output interface
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //get the exchange rates provider for the CurrencyBundle
        $exchangeRateProvider = $this
            ->getContainer()
            ->get('elcodi.core.currency.service.exchange_rates_provider');

        //get all available currencies
        $currencyRepository = $this
            ->getContainer()
            ->get('elcodi.repository.currency');

        //get base currency code
        $baseCurrencyCode = $this
            ->getContainer()
            ->getParameter(
                'elcodi.core.currency.default_currency'
            );

        //get the factory to get new CurrencyExchangeInterface instances
        $currencyExchangeRatesFactory = $this
            ->getContainer()
            ->get(
                'elcodi.core.currency.factory.currency_exchange_rate'
            );

        //get the manager
        $exchangeRatesObjectManager = $this
            ->getContainer()
            ->get('elcodi.object_manager.exchange_rate');

        //get all active currencies
        $currencies = $currencyRepository
            ->findBy([
                'enabled' => true
            ]);

        //extract target currency codes
        $currenciesCodes = array();
        foreach ($currencies as $activeCurrency) {
            if ($activeCurrency->getIso() != $baseCurrencyCode) {
                $currenciesCodes[] = $activeCurrency->getIso();
            }
        }

        //get rates for all of the enabled and active currencies
        $rates = $exchangeRateProvider->getExchangeRates($baseCurrencyCode, $currenciesCodes);

        $currencyExchangeRatesRepository = $this
            ->getContainer()
            ->get('elcodi.repository.currency_exchange_rate');

        foreach ($rates as $code => $rate) {

            $sourceCurrency = $currencyRepository->findOneByIso([
                'iso' => $baseCurrencyCode
            ]);

            $targetCurrency = $currencyRepository->findOneByIso([
                'iso' => $code
            ]);

            //check if this is a new exchange rate, or if we have to create a new one
            $exchangeRate = $currencyExchangeRatesRepository->findOneBy([
                'sourceCurrency' => $sourceCurrency,
                'targetCurrency' => $targetCurrency
            ]);

            if (!($exchangeRate instanceof CurrencyExchangeRateInterface)) {

                $exchangeRate = $currencyExchangeRatesFactory->create();
            }

            $exchangeRate->setExchangeRate($rate);
            $exchangeRate->setSourceCurrency($sourceCurrency);
            $exchangeRate->setTargetCurrency($targetCurrency);

            if (!$exchangeRate->getId()) {

                $exchangeRatesObjectManager->persist($exchangeRate);
            }
        }

        $exchangeRatesObjectManager->flush();
    }
}
