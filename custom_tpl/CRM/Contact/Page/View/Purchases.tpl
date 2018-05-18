<h3>Orders</h3>

<table class="selector row-highlight">
<thead class="sticky">
<tr>
 <th scope="col">{ts}Order Number{/ts}</th>
 <th scope="col">{ts}Date{/ts}</th>
 <th scope="col">{ts}Billing Name{/ts}</th>
 <th scope="col">{ts}Shipping Name{/ts}</th>
 <th scope="col">{ts}Item count{/ts}</th>
 <th scope="col">{ts}Amount{/ts}</th>
 <th scope="col">{ts}Actions{/ts}</th>
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
