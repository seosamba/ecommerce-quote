<template>
    <div v-show="loadedForm">
        <div id="tab-grid" v-show="loadedGrid">
            <div class="grid_12 alpha omega h360px">
                <table class="manage-saved-config-table responsive">
                    <thead>
                    <tr>
                        <th>{{$t('message.fieldType')}}</th>
                        <th>{{$t('message.fieldName')}}</th>
                        <th>{{$t('message.fieldLabel')}}</th>
                        <th class="text-right">{{$t('message.fieldAction')}}</th>
                    </tr>
                    </thead>
                    <tbody>
                        <tr v-for="configData in configDataInfo">
                            <td>{{configData.param_type}}</td>
                            <td>
                                <input type="text"  @change="updateCustomFieldNameLabel(configData.id, configData.param_name, configData.label, 'name', $event)" :value="configData.param_name">
                            <td>
                                <input type="text"  @change="updateCustomFieldNameLabel(configData.id, configData.param_name, configData.label, 'label', $event)" :value="configData.label">
                            </td>
                            <td class="text-right">
                                <span v-if="configData.param_type == 'select'">
                                    <a @click="editDropdownData(configData.id)" class="ticon-edit icon14" href="javascript:;"></a>
                                </span>
                                <a @click="deleteConfigItem(configData.id)" class="ticon-remove error icon14" href="javascript:;"></a>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <pagination sectionName="customFieldsConfig" @paginationHandler="$store.dispatch('getQuoteConfigSavedData', {})"></pagination>
            </div>
        </div>
        <div v-show="loadedGridAddNew">
            <form id="quote-custom-params-form" @submit.prevent="addCustomField" class="grid_12 alpha omega"
                  action="" method="POST">
                <fieldset class="background">
                    <legend class="background p5px">{{$t('message.addFieldToScreen')}}</legend>
                    <p class="grid_3 alpha omega mt0px">
                        <label>{{$t('message.customFeieldType')}}:</label>
                        <select @change="addDropdownProperty(param_type)" id="quote-field-type" class="required" name="param_type" v-model="param_type">
                            <option value="text">{{$t('message.textField')}}</option>
                            <option value="select">{{$t('message.dropdownField')}}</option>
                        </select>
                    </p>
                    <p class="grid_3 omega mt0px">
                        <label>{{$t('message.customFieldName')}}:</label>
                        <input class="required param_name" @keyup="toLabel" type="text" name="param_name" v-model.trim="param_name" value=""/>
                    </p>
                    <p class="grid_3 omega mt0px">
                        <label>{{$t('message.customFieldLabel')}}:</label>
                        <input class="required param_label" @keyup="toLabel" type="text" name="label" :value="label"/>
                    </p>
                    <p class="grid_3 omega mt0px">
                        <input id="quote-custom-params-form-save" class="btn mt20px" type="submit" name="quote-custom-params-form-save"
                               :value="[$t('message.quoteCustomParamsFormAdd')]"/>
                    </p>
                </fieldset>
            </form>
        </div>
        <div v-show="loadedDropdownForm">
            <div class="back-link grid_12 mb10px" id="back-link">
                <a @click="backToMainGrid" href="javascript:;" class="btn" id="custom-fields-grid-back"><span class="icon-arrow-left">{{$t('message.back')}}</span></a>
            </div>
            <form id="quote-custom-params-form-dropdown" @submit.prevent="saveDropdown" class="grid_12 alpha omega" action="" method="POST">
                <div id="manage-quote-dropdown-container" class=" grid_12 alpha omega mt0px">
                    <div>
                        <div id="options-holder" class="grid_12 alpha omega">
                            <div class="grid_12 mt10px">
                                <div class="grid_6 alpha">
                                    <label class="grid_5 alpha omega mt5px">{{$t('message.customFieldName')}}</label>
                                    <input v-model.trim="param_name" @keyup="toLabel" class="required grid_7 alpha omega param_name" type="text" name="param_name" value="">
                                </div>
                                <div class="grid_6 omega">
                                    <label class="grid_5 alpha omega mt5px">{{$t('message.customFieldLabel')}}</label>
                                    <input class="required grid_7 alpha omega param_label" @keyup="toLabel" type="text" name="label" :value="label">
                                </div>
                            </div>
                        </div>
                        <div class="grid_12 background mt10px">
                            <div class="header-inner grid_12 alpha omega">
                                <div class="grid_10">{{$t('message.title')}}</div>
                            </div>
                            <div class="option-list-holder grid_12 alpha omega scroll" style="max-height: 310px;">
                                <div class="grid_12 alpha omega mt10px" v-for="(selectionData, index) in selectionEl">
                                    <input class="grid_10 alpha omega" type="text" v-model.trim="selectionData.name"
                                           :name="selectionData.name" :placeholder="selectionData.placeholder">
                                    <div class="grid_2 text-center">
                                        <span class="btn item-remove btn icon link error ticon-remove mt0px" @click="deleteSelectionData(index)"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="grid_12">
                            <a @click="addNewSelection" class="add-selection-btn grid_12 alpha omega mt10px btn success ticon-plus" href="javascript:;">{{$t('message.addNewSelection')}}</a>
                        </div>
                        <p class="grid_12 fl-right">
                            <input class="btn mt20px" type="submit" name="quote-custom-params-form"
                                   :value="[$t('message.quoteCustomParamsFormSave')]"/>
                        </p>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<script src="./controller/general-config.js"/>




