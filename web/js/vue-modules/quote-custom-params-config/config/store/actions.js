export const saveConfigData = ({commit, state, dispatch}, payload) => {
    showLoader();
    return new Promise((resolve, reject) => {
        $.ajax({
            'url': $('#website_url').val() + 'api/quote/Quotecustomfieldsconfig/',
            'type': 'POST',
            'dataType': 'json',
            'data': {
                'secureToken'   : $('#quote-custom-params-config-token').val(),
                'param_type'    : payload.param_type,
                'param_name'    : payload.param_name,
                'label'         : payload.label,
                'dropdownParams': payload.dropdownParams
            }
        }).done(async function (response) {
            hideLoader();
            if (response.status === 'error') {
                resolve(response);
            } else {
                resolve(response);
            }

        }).fail(async function(response){
            hideLoader();
            resolve({ name: 'login', 'message': 'Please re-login'});
        });
    });
};

export const updateConfigData = ({commit, state, dispatch}, payload) => {
    showLoader();
    return new Promise((resolve, reject) => {
        $.ajax({
            'url': $('#website_url').val() + 'api/quote/Quotecustomfieldsconfig/',
            'type': 'PUT',
            'dataType': 'json',
            'data': JSON.stringify({
                'secureToken'   : $('#quote-custom-params-config-token').val(),
                'param_type'    : payload.param_type,
                'param_name'    : payload.param_name,
                'label'         : payload.label,
                'dropdownParams': payload.dropdownParams,
                'id'            : payload.dropdownId
            })
        }).done(async function (response) {
            hideLoader();
            if (response.status === 'error') {
                resolve(response);
            } else {
                resolve(response);
            }
        }).fail(async function(response){
            hideLoader();
            resolve({ name: 'login', 'message': 'Please re-login'});
        });
    });
};

export const deleteConfigRecord = ({commit, state, dispatch}, payload) => {
        showLoader();
        return new Promise((resolve, reject) => {
            $.ajax({
                'url': $('#website_url').val() + 'api/quote/Quotecustomfieldsconfig/id/' + payload.id+'/secureToken/'+ $('#quote-custom-params-config-token').val(),
                'type': 'DELETE',
                'dataType': 'json'
            }).done(async function (response) {
                hideLoader();
                if (response.status === 'error') {
                    resolve(response);
                } else {
                    resolve(response);
                }
            }).fail(async function (response) {
                hideLoader();
                resolve({name: 'login', 'message': 'Please re-login'});
            });
        });
};

export const getQuoteConfigSavedData = ({commit, state, dispatch}, payload) => {
    showLoader();
    return new Promise((resolve, reject) => {
        $.ajax({
            'url': $('#website_url').val()+'api/quote/Quotecustomfieldsconfig/',
            'type': 'GET',
            'dataType': 'json',
            'data': {
                'limit' : state.pagination.customFieldsConfig.itemsPerPage,
                'offset': (state.pagination.customFieldsConfig.currentPage - 1) * state.pagination.customFieldsConfig.itemsPerPage
            }
        }).done(async  function(response){
            hideLoader();
            if (response.status !== 'error') {
                commit('setPaginationData', {customFieldsConfig: {totalItems: response.totalRecords}});
                commit('setConfigDataInfo', response.data);
                resolve(response);
            } else {
                resolve({ name: 'login', 'message': 'Please re-login'});
            }
        }).fail(async function(response){
            resolve({ name: 'login', 'message': 'Please re-login'});
        });
    });
};

export const updateCustomFieldData = ({commit, state, dispatch}, payload) => {
    showLoader();
    return new Promise((resolve, reject) => {
        $.ajax({
            'url': $('#website_url').val()+'api/quote/Quotecustomfieldsconfig/',
            'type': 'PUT',
            'dataType': 'json',
            'data': JSON.stringify({
                'id'         : payload.id,
                'param_name' : payload.customFieldName,
                'label'      : payload.customFieldLabel,
                'secureToken': $('#quote-custom-params-config-token').val()
            })
        }).done(function(response){
            hideLoader();
            if (response.status === 'error') {
                resolve(response);
            } else {
                resolve(response);
            }
        }).fail(async function(response){
            hideLoader();
            resolve({ name: 'login', 'message': 'Please re-login'});
        });
    });
};

export const getSavedDropdownConfig = ({commit, state, dispatch}, payload) => {
    showLoader();
    return new Promise((resolve, reject) => {
        $.ajax({
            'url': $('#website_url').val()+'api/quote/Quotecustomfieldsconfig/',
            'type': 'GET',
            'dataType': 'json',
            'data': {
                'id': payload.dropdownId
            }
        }).done(async  function(response){
            hideLoader();
            if (response.status !== 'error') {
                resolve(response);
            } else {
                resolve({ name: 'login', 'message': 'Please re-login'});
            }
        }).fail(async function(response){
            resolve({ name: 'login', 'message': 'Please re-login'});
        });
    });
};
