import {mapGetters} from 'vuex';
import lodash from 'lodash';
import moment from 'moment';
export default {
    data () {
        return {
            loadedDropdownForm: true,
            param_type: 'select',
            param_name: '',
            label: '',
            selectionEl: [],
            loaded: false,
            websiteUrl: $('#website_url').val(),
            dropdownId : '',
            locale: $('#system-language-quote-custom-fields').val(),
            localeMapping: {
                'en':'en',
                'en_US':'en',
                'es':'es',
                'es_ES':'es',
                'fr':'fr',
                'fr_FR':'fr'
            }
        }
    },
    components: {

    },
    computed: {
    },
    methods: {
        alphabeticalSort: function(obj){
            // convert object into array
            var sortable=[];
            for(var key in obj)
                if(obj.hasOwnProperty(key))
                    sortable.push([key, obj[key]]); // each item is an array in format [key, value]

            // sort items by value
            sortable.sort(function(a, b)
            {
                var x=a[1].toLowerCase(),
                    y=b[1].toLowerCase();
                return x<y ? -1 : x>y ? 1 : 0;
            });
            return sortable; // array in format [ [ key1, val1 ], [ key2, val2 ], ... ]
        },
        backToMainGrid: function () {
            this.loadedDropdownForm = false;
            this.$router.push({ name: 'index'});
        },
        toLabel: function(e)
        {
            let currentLabel = $(e.currentTarget).val();
            this.label = currentLabel;
        },
        async saveDropdown() {
            if (this.selectionEl.length == '0') {
                showMessage(this.$t('message.specifySelectionEl'), true, 2000);
                return false;
            } else {
                for(let key in this.selectionEl) {
                    let filteredOptionName = this.selectionEl[key].name.replace(/[^a-zA-Z0-9'-_ ]/g, '');
                    if(filteredOptionName == '') {
                        showMessage(this.$t('message.specifyOptionName'), true, 2000);
                        return false;
                    } else {
                        this.selectionEl[key].value = filteredOptionName;
                    }
                }
            }

            let customFieldNameFiltered = this.param_name.replace(/[^a-zA-Z0-9'-_ ]/g, '');
            let customFieldLabelFiltered = this.label.replace(/[^a-zA-Z0-9'-_ ]/g, '');

            if (customFieldNameFiltered == '') {
                showMessage(this.$t('message.specifyParamName'), true, 2000);
                return false;
            }

            if (customFieldLabelFiltered == '') {
                showMessage(this.$t('message.specifLabel'), true, 2000);
                return false;
            }

            if(this.dropdownId == '') {
                showMessage(this.$t('message.updateError'), true, 2000);
                return false;
            }

            const result = await this.$store.dispatch('updateConfigData', {
                'dropdownId': this.dropdownId,
                'param_type': 'select',
                'param_name':customFieldNameFiltered,
                'label':customFieldLabelFiltered,
                'dropdownParams':this.selectionEl
            });

            if (result.status === 'error') {
                showMessage(result.message, true, 2000);
                return false;
            } else {
                showMessage(result.message, false, 2000);
                const resultConfigData = await this.$store.dispatch('getQuoteConfigSavedData', {'router':this.$router});
                if(result.status === 'error') {

                } else {
                    this.backToMainGrid();
                }
            }
        },
        deleteSelectionData: function(index)
        {
            if(typeof this.selectionEl[index].id !== 'undefined') {
                showConfirm(this.$t('message.actionConfirmationDeleteSelect'), async () => {
                    this.selectionEl.splice(index,1);
                });
            } else {
                this.selectionEl.splice(index,1);
            }
        },
        addNewSelection: function () {
            this.selectionEl.push({
                'name': '',
                'placeholder' : this.$t('message.provideOptionName')
            });
        }
    },
    async created(){
        this.$i18n.locale = this.localeMapping[this.locale];
        this.dropdownId = this.$route.params.id;
        const result = await this.$store.dispatch('getSavedDropdownConfig', {
            'router': this.$router,
            'dropdownId': this.dropdownId
        });

        if(result.status === 'error') {
            this.$router.push({ name: 'index'});
        } else {
            this.loaded = true;

            let resultData = result.data;
            let self = this;

            for (var key in resultData) {
                self.param_name = resultData[key].param_name;
                self.label = resultData[key].label;

                var optionIds = resultData[key].option_ids.split(',');
                let optionValues = resultData[key].option_values.split(',');

                if(optionValues.length) {
                    for (var optKey in optionValues) {
                        self.selectionEl.push({
                            'id' : optionIds[optKey],
                            'name': optionValues[optKey],
                            'placeholder' : this.$t('message.provideOptionName')
                        });
                    }
                }
            }
        }
    }
}
