/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://www.statik.be
 * @copyright Copyright (c) 2017 Statik.be
 */

(function($)
{
    /**
     * StatikTranslate class
     */
    var StatikTranslate = Garnish.Base.extend({

        options: null,
        $toHeader:null,
        $menu: null,
        $form: null,

        /**
         * The constructor.
         */
        init: function()
        {
            this.addListener($('#save-elements-button'), 'activate', 'processAjaxCall');

            //uitgebreide functie om te luisteren naar changes in url
            ;(function (){
                let oldPushState = history.pushState;
                history.pushState = function pushState() {
                    let ret = oldPushState.apply(this, arguments);
                    window.dispatchEvent(new Event('pushstate'));
                    window.dispatchEvent(new Event('locationchange'));
                    return ret;
                };

                let oldReplaceState = history.replaceState;
                history.replaceState = function replaceState() {
                    let ret = oldReplaceState.apply(this, arguments);
                    window.dispatchEvent(new Event('replacestate'));
                    window.dispatchEvent(new Event('locationchange'));
                    return ret;
                };

                window.addEventListener('popstate', () => {
                    window.dispatchEvent(new Event('locationchange'));
                });
            })();

            //haal search query uit url
            let query = window.location.search;

            //splits query op naar enkel de site
            let q = query.split("&");

            //haal site handle uit query split
            let language = q[0].split("=");
            let lang = language[1];

            //als url veranderd verander dan ook de banner naar de juiste site handle
            window.addEventListener('locationchange', function(){
                //vergelijk de huidige tekst in de banner met de handle in de url
                if(document.querySelector(".sitemenubtn").innerHTML != lang){
                    document.querySelector(".currentTranslation span").innerHTML = document.querySelector(".sitemenubtn").innerHTML;
                }
            });

            this.$form = $("#translate-ajax");


            //save with short cut cmd + S or ctrl + S
            this.addListener(Garnish.$doc, 'keydown', function(ev)
            {
                if ((ev.metaKey || ev.ctrlKey) && ev.keyCode == 83)
                {
                    ev.preventDefault();
                    this.processAjaxCall(event);
                }
            });

            var settings = {};
            this.$menu = new Garnish.MenuBtn("#statik-menubtn", settings);

            var $siteMenu = $('.sitemenubtn:first').menubtn().data('menubtn') ? $('.sitemenubtn:first').menubtn().data('menubtn').menu : false;
            var $siteIdInput = $('input[name="siteId"]');

            // Upload file on click
            $('.translations-upload-button').click(function() {
                $('input[name="translations-upload"]').click().change(function() {
                    $(this).parent('form').submit();
                });
            });

            // Init the form
            let siteId = this.getSiteId();
            //$siteIdInput.val(siteId);

            // Change the siteId when on hidden values
            if ($siteMenu) {
                $siteMenu.on('optionselect', function(ev) {
                    $siteIdInput.val($(ev.selectedOption).data('siteId'));
                });
            }

            Craft.elementIndex.on('afterAction', this.manageAfterAction);
            this.$menu.on('optionSelect', this.manageMenu);
        },

        submitPrimaryForm: function (event) {
            this.processAjaxCall(event);
        },

        getSiteId: function() {
            // If the old BaseElementIndex.siteId value is in localStorage, go ahead and remove & return that
            let siteId = Craft.getLocalStorage('BaseElementIndex.siteId');
            if (typeof siteId !== 'undefined') {
                Craft.removeLocalStorage('BaseElementIndex.siteId');
                this.setSiteId(siteId);
                return siteId;
            }
            return Craft.getCookie('siteId');
        },

        manageMenu: function(event)
        {
            var data = {
                siteId: Craft.elementIndex.siteId,
                sourceKey: Craft.elementIndex.sourceKey
            };

            Craft.postActionRequest('translate/translate/download', data, $.proxy(function(response, textStatus) {
                if (textStatus === 'success') {
                    if (response.success)
                    {
                        if (response.filePath){
                            var $iframe = $('<iframe/>', {'src': Craft.getActionUrl('translate/translate/download-csv-file', {'filepath': response.filePath})}).hide();
                            $("#translate-ajax").append($iframe);
                            Craft.cp.displayNotice(Craft.t('translate', 'Downloading file'));
                        }
                        else {
                            Craft.cp.displayError(Craft.t('app', 'There was an error when generating the file'));
                        }
                    }
                    else {
                        Craft.cp.displayError(Craft.t('app', 'Please select a different source'));
                    }
                }
                else {
                    Craft.cp.displayError(Craft.t('app', 'An unknown error occurred.'));
                }
            }, this));
        },

        manageAfterAction: function(action, params)
        {
            Craft.elementIndex.updateElements();
        },

        processAjaxCall: function(event)
        {
            $('.flex .spinner').removeClass('invisible');
            event.preventDefault();
            var data = this.$form.serializeArray();
            data.push({name: 'sourceKey', value: Craft.elementIndex.sourceKey});
            Craft.postActionRequest('translate/translate/save', data, $.proxy(function (response, textStatus) {
                $('.flex .spinner').addClass('invisible');
                if (textStatus === 'success') {
                    if (response.success) {
                        Craft.cp.displayNotice(Craft.t('translate', 'Translations saved'));
                    }
                } else {
                    Craft.cp.displayError(Craft.t('app', 'An unknown error occurred.'));
                }
            }, this));
        }
    });

    window.StatikTranslate = StatikTranslate;

})(jQuery);