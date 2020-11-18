{if isset($newOrderUrl)}
<h3>{ts}{$i18n.orders}{/ts} <a class="button-new_order button" href="{$newOrderUrl}">{ts}{$i18n.addOrder}{/ts}</a></h3>
{/if}
<table class="selector row-highlight">
<thead class="sticky">
<tr>
 <th scope="col">{ts}{$i18n.orderNumber}{/ts}</th>
 <th scope="col">{ts}{$i18n.date}{/ts}</th>
 <th scope="col">{ts}{$i18n.billingName}{/ts}</th>
 <th scope="col">{ts}{$i18n.shippingName}{/ts}</th>
 <th scope="col">{ts}{$i18n.itemCount}{/ts}</th>
 <th scope="col">{ts}{$i18n.amount}{/ts}</th>
 <th scope="col">{ts}{$i18n.actions}{/ts}</th>
</tr>
</thead>
<tbody>
{foreach from=$orders item=row}
{assign var=id value=$row.order_number}
<tr class="{$row.order_status}">
  <td>{$row.order_number}</td>
  <td>{$row.order_date}</td>
  <td>{$row.order_billing_name}</td>
  <td>{$row.order_shipping_name}</td>
  <td>{$row.item_count}</td>
  <td>{$row.order_total|crmMoney}</td>
  <td>
      <a href="{$row.order_link}">Edit</a>
  </td>
</tr>
{/foreach}
{literal}
<script type="text/javascript">console.log('Loaded')</script>
{/literal}
</tbody>
</table>
