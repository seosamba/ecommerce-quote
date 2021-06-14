<template>
    <main class="grid_12" v-show="loaded">
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
                                    <input v-model.trim="param_name" class="required grid_7 alpha omega param_name" @keyup="toLabel" type="text" name="param_name" value="">
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
                                        <span :id="selectionData.id" class="btn item-remove btn icon link error ticon-remove mt0px" @click="deleteSelectionData(index)" ></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="grid_12">
                            <a @click="addNewSelection" class="add-selection-btn grid_12 alpha omega mt10px btn success ticon-plus" href="javascript:;">{{$t('message.addNewSelection')}}</a>
                        </div>
                        <p class="grid_12 fl-right">
                            <input class="btn mt20px" type="submit" name="quote-custom-params-form"
                                   value="Add custom field"/>
                        </p>
                    </div>
                </div>
            </form>
        </div>
    </main>
</template>

<script src="./controller/dropdowndetails.js"/>
