# SDEK Shipping method for Woocommerce [![Build Status](https://travis-ci.org/kalbac/wc-edostavka.svg)](https://travis-ci.org/kalbac/wc-edostavka)

## Description

Плагин [WC eDostavka](https://github.com/kalbac/wc-edostavka) добавляет метод доствки для курьерской службы [СДЕК](http://edostavka.ru)

**NOTICE**
В связи с глобальным обновлением Woocommerce с версии 3+, ниодна из доступных версий плагина WC eDostavka не работает!
Версия плагина совместимая с версиями Woocommerce 3+ доступна теперь только платно. Для этого вам нужно связаться со мной по электоной почте maksim[at]martirosoff.ru и оплатить через сервис Яндекс.Деньги перейдя по [ссылке](https://money.yandex.ru/to/41001231735306/1600) при этом в комментарии обязательно напишите свою почту на которую вам отправить плагин.
Работу плагина вы можете посмотреть на сайте http://cdek.woodev.ru

О поддержке плагина вы можете почитать [тут](https://github.com/kalbac/wc-edostavka/wiki/%D0%9F%D0%BE%D0%B4%D0%B4%D0%B5%D1%80%D0%B6%D0%BA%D0%B0-%D0%BF%D0%BB%D0%B0%D0%B3%D0%B8%D0%BD%D0%B0)

**Информация ниже актуальна только для версий Woocommerce до 3.0.0**

**Внимание** для правильной работы плагина необходимы:

**Для версии плагина ниже 1.3.2**
* Wordpress не ниже 4.2
* Woocommerce не ниже 2.3
* PHP не ниже 5.3

**Для версии плагина выше 1.3.6**
* Wordpress не ниже 4.6
* Woocommerce не ниже 2.5.4
* PHP не ниже 5.3

**Примечание**

Плагин добалвяет в вашу корзину способы доставки  службой СДЕК и рассчитывает их стоимость. 
Задача плагина именно рассчитать цену за тариф. Он не формирует автоматиечки заявку в курьерской службе. Заявки вы должны составлять самостоятельно в личном кбаинете на сайте edostavka.ru

Плагин разрабатывался и тестировался на чистом Wordpress с Woocommerce. 
При возниконовении проблем:

* Проверьте корректность настроек
* Проверьте не конлфиктует ли с плагин с каким то другим плагином или вашей активной темой (шаблоном).

## Install

* Скачайте последнюю версию плагина на странице [GitHub](https://github.com/kalbac/wc-edostavka/releases/latest)
* **Вариант №1**
 1. Распакуйте плагин в папку `wp-plugins` на вашем web-сервере
 2. Зайдите в панель управления вашего сайта, в раздел плагины, найдите в списке не активных плагинов плагин *eDostavka Shipping Method* и активируйте его.
* **Вариант №2**
 1. Скачайте .ZIP архив на ваш компьютер.
 2. Зайдите в панель управления вашего сайта, в разделе плагины, выберите пунтк "Добавить плагин".
 3. Укажите путь к скаченному ранее .ZIP архиву
 4. Загрузите архив на сервер.
 5. Активируйте плагин.
 
## USAGE

Далее для корректной работы плагина необходимо настроить.
 1. На странице настроек плагина (https://адресмоегосайта/wp-admin/admin.php?page=wc-settings&tab=shipping&section=edostavka) введите API логин и API ключ который вам выдали в СДЭК.
 2. Обазательно, в выпадающем списке выбирите город отправитель.
 ![Основные настройки](http://i.imgur.com/LvJsOv6.png?1)
 3. По желанию укажите размеры одного товара по умаолчанию. (при отсуствии габаритов сервер сдек возвращает ошибку).
 4. Создайте зону доставки, если у вас таковой нету.
 5. В эту зону доствки добавьте метод доставки СДЭК.
 ![Настройки зон](http://i.imgur.com/BjsDy1V.png)
 6. На забываем отблагодарить автора по [ссылке](https://money.yandex.ru/embed/donate.xml?account=41001231735306&quickpay=donate&payment-type-choice=on&default-sum=1000&targets=%D0%9F%D0%BE%D0%B6%D0%B5%D1%80%D0%B2%D0%BE%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5+%D0%BD%D0%B0+%D0%BF%D0%BE%D0%B4%D0%B4%D0%B5%D1%80%D0%B6%D0%BA%D1%83+%D0%BF%D0%BB%D0%B0%D0%B3%D0%B8%D0%BD%D0%B0+WC+eDostavka&target-visibility=on&button-text=05) 
  

## Issues

Вопросы и предложения пишите в раздел [Issues](https://github.com/kalbac/wc-edostavka/issues)

## Showcase
* [Для версии Woocommerce 3+](http://cdek.woodev.ru)
* [WooDev](http://woodev.ru/)
* [N-one shop](https://n-one.ru/)
* [Fitnfood](https://fitnfood.ru/)
* [4sport](http://4ksports.ru/)

## Documentation for development

* [Woo Codex Docs](https://docs.woothemes.com/documentation/woocodex/)
* [WordPress Codex](http://codex.wordpress.org/)
 
