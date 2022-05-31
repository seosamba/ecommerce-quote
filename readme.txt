E-commerce quote plugin

Used for creating quotes directly from page or at the checkout step.

Quote widgets:

Widgets on product page and checkout:
{$quote:form[:customfields:[fieldName,fieldName2,...]]} - Displays a quote form at the checkout page (users can request a quote on a few products at the same time) or directly on the product page template.
customfields:fieldName,fieldName2 - display quote custom fields

Widgets that working with quote page (dashboard):
{$quote:search} - Displays the search form on the quote page.
{$quote:address:billing[:required-lastname,company,address1,address2,city,zip,mobile,phone][:customfields:fieldName]} - Displays the form to input user's address which used as user's payment info. (for admin)
customfields:fieldName - display quote custom field
{$quote:address:shipping[:required-lastname,company,address1,address2,city,zip,mobile,phone]} - Displays a form to enter the user's shipping address. (for admin)
{$quote:title} - Displays the quote name.
{$quote:creator} - Displays creator name
{$quote:editor} - Displays editor name
{$quote:date:created} - Displays the date of quote creation.
{$quote:date:expires} - Displays the quote expiration date.
{$quote:total[:tax|sub|grand|totalwotax|taxdiscount]} - Displays the total price in the cart. If the option :grand is set then discount and shipping will be added to the price.
{$quote:shipping} - Displays the shipping price.
{$quote:discount} - Displays the product discount.
{$quote:controls} - Displays buttons "add product", "save the quote", "save and send the quote".
{$quote:userid} - Reture quote user ID
{$quote:timestamp[:created|expires][:m-d-Y h:i]} - return quote creation timestamp or expires timestamp
{$quote:address[:customfields:fieldName]}
customfields:fieldName - display quote custom field

Widgets that used into {toasterquote}{/toasterquote} magicspace. Also working with quote page (dashboard):
{$quote:item:name} - Displays the product name added to the quote.
{$quote:item:options} - Displays product options that was added to the quote.
{$quote:item:id} - Displays product id that was added to the quote.
{$quote:item:qty} - Displays number of the same products.
{$quote:item:price[:unit]} - Displays the product price.
 :unit - price for single unit
{$quote:item:remove} - Displays remove button.
{$quote:item:sid} - Unique product hash (product name + product sku + product options).
{$quote:item:photo[:link]} - Displays product photo.
:link - link to product page.

{$quote:customfield:customfields:fieldName,fieldName2[:{$quote:cartId}[:readonly]]}
customfields:fieldName,fieldName2 - display quote custom fields
{$quote:cartId} - if cartId option isn't added, widget will try to find quote cartId automatically
readonly - display only custom field text

MAGICSPACE: customersonly
{customersonly}{/customersonly} - return content for everyone who not have access to the storemanagement resource
Ex: guest, customer, member
{customersonly}
<div class="quote-info" id="quote-billing-info">
    <p class="title">billing address</p>
    <p>{$quote:address:billing:prefix} {$quote:address:billing:firstname} {$quote:address:billing:lastname}</p>
    <p>{$quote:address:billing:company}</p>
    <p>{$quote:address:billing:address1} {$quote:address:billing:address2}</p>
    <p>{$quote:address:billing:city} {$quote:address:billing:state} {$quote:address:billing:zip}</p>
    <p>{$quote:address:billing:country}</p>
    <p><a href="mailto:{$quote:address:billing:email}">{$quote:address:billing:email}</a></p>
    <p>{$quote:address:billing:phone}</p>
</div>
<div class="quote-info" id="quote-shipping-info">
    <p class="title">shipping address</p>
    <p>{$quote:address:shipping:prefix} {$quote:address:shipping:firstname} {$quote:address:shipping:lastname}</p>
    <p>{$quote:address:shipping:company}</p>
    <p>{$quote:address:shipping:address1} {$quote:address:shipping:address2}</p>
    <p>{$quote:address:shipping:city} {$quote:address:shipping:state} {$quote:address:shipping:zip}</p>
    <p>{$quote:address:shipping:country}</p>
    <p><a href="mailto:{$quote:address:shipping:email}">{$quote:address:shipping:email}</a></p>
    <p>{$quote:address:shipping:phone}</p>
</div>
{/customersonly}

MAGICSPACE: quoteexpired
{quoteexpired}{/quoteexpired} - display content if quote have status lost
{quoteexpired:not}{/quoteexpired} - display content if quote doesn't have status lost

MAGICSPACE: toasterquote
Renders magic space using quote template
Here you can put quote item widgets
  <tbody id="quote-sortable">
      {toasterquote}
      <tr class="quote-sortable-product-row" data-sort-product-sid="{$quote:item:sid}">
          <td class="product-img"> {$quote:item:photo} </td>
          <td class="product-info"><p class="item-name">{$quote:item:name}</p>
              <p>{$quote:item:shortDescription}</p>
              <p class="itemID"><span>Item ID: </span>{$quote:item:sku}</p>
              <div class="product-options">{$quote:item:options}</div>
              {$content:lalalala-{$quote:item:sid}-note}
          </td>
          <td class="product-qty">{$quote:item:qty}</td>
          <td class="product-unit-price">{$quote:item:price:unit}</td>
          <td class="product-total">{$quote:item:price}</td>
          <td class="product-remove">{$quote:item:remove}</td>
      </tr>
      {/toasterquote}
  </tbody>

 id="quote-sortable" - special ID to allow use draggable products.
 data-sort-product-sid="{$quote:item:sid}" - unique product hash with options.

 MAGICSPACE: quotediscount
  {quotediscount}{/quotediscount} - Hide content if quote discount doesn't exist. Doesn't work for everyone who have admin access.

Quote action emails lexems:
{quoteowner:email} - This lexem return quote owner email address
{widcard:BizEmail} - This lexem return widcard email address
{$quote:item:option:optionName} -> option without title
{$quote:item:option:optionName:title} -> option with title
:optionName - option name

{orderstatus[:partial,completed,...]}
{paymenttype[:full_payment|full_payment_signature|partial_payment|partial_payment_signature|only_signature]]}
{quoteadminonly}{/quoteadminonly} - return content for everyone who have access to the storemanagement resource
{$quote:secondpaymentamount} - second payment amount
{quoteleadorganizationlogo} - This lexem return quote lead organization logo
{quoteleadorganizationlogo:src} - This lexem return quote lead organization logo src
{quotecustomfields:fieldName} - This lexem return quote customfield where "fieldName" is customfield name
{pickuponly}{/pickuponly} - display if cart has pickup
{pickuponly:not}{/pickuponly} - not display if cart has pickup


{quoterestrictedcontrol}{/quoterestrictedcontrol} - display content if quote has restricted access control

{$quote:signature:signature-info-field} - display additional info field signature

{$quote:singatureadditionalinfo} - displays field with signature additional info

{postpurchasequotecode} ... {/postpurchasequotecode} - displays content if purchase was made via the quote system