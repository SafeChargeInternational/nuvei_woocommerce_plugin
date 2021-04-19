<style>
    .nuvei_meta_fileds input {
        -moz-appearance: textfield !important;
    }

    endAfter.nuvei_meta_fileds input::-webkit-outer-spin-button,
    endAfter.nuvei_meta_fileds input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    
    select.nuvei_units {
        float: left;
        margin-right: 10px;
    }
    
    th {
        text-align: left;
    }
</style>

<tr class="nuvei_meta_fileds">
    <th><?= __('Plan ID', 'nuvei_woocommerce'); ?></th>
    <td>
        <select name="planId" id="planId" onclick="nuveiFillPlanData(this.value)">
            <?php if(!empty($plans_list)): 
                foreach ($plans_list as $plan): ?>
                    <option value="<?= $plan['planId']; ?>" <?= (current($term_meta['planId']) == $plan['planId'] ? 'selected=""' : ''); ?>><?= $plan['name']; ?></option>
                <?php endforeach;
            endif; ?>
        </select>
    </td>
</tr>

<tr class="nuvei_meta_fileds">
    <th><?= __('Recurring Amount', 'nuvei_woocommerce'); ?></th>
    <td>
        <input type="number" step="1" min="0.01" name="recurringAmount" value="<?= current($term_meta['recurringAmount']); ?>" required="" />
    </td>
</tr>

<tr class="nuvei_meta_fileds">
    <th><?= __('Recurring Period', 'nuvei_woocommerce'); ?></th>
    <td>
        <select name="recurringPeriodUnit" id="recurringPeriodUnit" class="nuvei_units">
            <option value="day" <?= (current($term_meta['recurringPeriodUnit']) == 'day' ? 'selected=""' : ''); ?>><?= __('Days', 'nuvei_woocommerce'); ?></option>
            <option value="month" <?= (current($term_meta['recurringPeriodUnit']) == 'month' ? 'selected=""' : ''); ?>><?= __('Month', 'nuvei_woocommerce'); ?></option>
            <option value="year"  <?= (current($term_meta['recurringPeriodUnit']) == 'year' ? 'selected=""' : ''); ?>><?= __('Years', 'nuvei_woocommerce'); ?></option>
        </select>
        
        <input type="number" step="1" min="1" name="recurringPeriodPeriod" id="recurringPeriodPeriod" value="<?= current($term_meta['recurringPeriodPeriod']); ?>" required="" />
    </td>
</tr>

<tr class="nuvei_meta_fileds">
    <th><?= __('Recurring End After', 'nuvei_woocommerce'); ?></th>
    <td>
        <select name="endAfterUnit" id="endAfterUnit" class="nuvei_units">
            <option value="day" <?= (current($term_meta['endAfterUnit']) == 'day' ? 'selected=""' : ''); ?>><?= __('Days', 'nuvei_woocommerce'); ?></option>
            <option value="month" <?= (current($term_meta['endAfterUnit']) == 'month' ? 'selected=""' : ''); ?>><?= __('Month', 'nuvei_woocommerce'); ?></option>
            <option value="year" <?= (current($term_meta['endAfterUnit']) == 'year' ? 'selected=""' : ''); ?>><?= __('Years', 'nuvei_woocommerce'); ?></option>
        </select>
        
        <input type="number" step="1" min="1" name="endAfterPeriod" id="endAfterPeriod" value="<?= current($term_meta['endAfterPeriod']); ?>" required="" />
    </td>
</tr>

<tr class="nuvei_meta_fileds">
    <th><?= __('Trial Period', 'nuvei_woocommerce'); ?></th>
    <td>
        <select name="startAfterUnit" id="startAfterUnit" class="nuvei_units" required="">
            <option value="day" <?= (current($term_meta['startAfterUnit']) == 'day' ? 'selected=""' : ''); ?>><?= __('Days', 'nuvei_woocommerce'); ?></option>
            <option value="month" <?= (current($term_meta['startAfterUnit']) == 'month' ? 'selected=""' : ''); ?>><?= __('Month', 'nuvei_woocommerce'); ?></option>
            <option value="year" <?= (current($term_meta['startAfterUnit']) == 'year' ? 'selected=""' : ''); ?>><?= __('Years', 'nuvei_woocommerce'); ?></option>
        </select>
        
        <input type="number" step="1" min="1" name="startAfterPeriod" id="startAfterPeriod" value="<?= current($term_meta['startAfterPeriod']); ?>" required="" />
    </td>
</tr>

<script>
    var nuveiPlans = JSON.parse('<?= $plans_json; ?>');
    
    function nuveiFillPlanData(_planId) {
        if('' == _planId) {
            return;
        }
        
        for(var nuveiPlData in nuveiPlans) {
            if(_planId == nuveiPlans[nuveiPlData].planId) {
                // Recurring Amount
                jQuery('#recurringAmount').val(nuveiPlans[nuveiPlData].recurringAmount);
                
                // Recurring Units and Period
                if(nuveiPlans[nuveiPlData].recurringPeriod.year > 0) {
                    jQuery('#recurringPeriodUnit').val('year');
                    jQuery('#recurringPeriodPeriod').val(nuveiPlans[nuveiPlData].recurringPeriod.year);
                }
                else if(nuveiPlans[nuveiPlData].recurringPeriod.month > 0) {
                    jQuery('#recurringPeriodUnit').val('month');
                    jQuery('#recurringPeriodPeriod').val(nuveiPlans[nuveiPlData].recurringPeriod.month);
                }
                else {
                    jQuery('#recurringPeriodUnit').val('day');
                    jQuery('#recurringPeriodPeriod').val(nuveiPlans[nuveiPlData].recurringPeriod.day);
                }
                
                // Recurring End-After Units and Period
                if(nuveiPlans[nuveiPlData].endAfter.year > 0) {
                    jQuery('#endAfterUnit').val('year');
                    jQuery('#endAfterPeriod').val(nuveiPlans[nuveiPlData].endAfter.year);
                }
                else if(nuveiPlans[nuveiPlData].endAfter.month > 0) {
                    jQuery('#endAfterUnit').val('month');
                    jQuery('#endAfterPeriod').val(nuveiPlans[nuveiPlData].endAfter.month);
                }
                else {
                    jQuery('#endAfterUnit').val('day');
                    jQuery('#endAfterPeriod').val(nuveiPlans[nuveiPlData].endAfter.day);
                }
                
                // Recurring Trial Units and Period
                if(nuveiPlans[nuveiPlData].startAfter.year > 0) {
                    jQuery('#startAfterUnit').val('year');
                    jQuery('#startAfterPeriod').val(nuveiPlans[nuveiPlData].startAfter.year);
                }
                else if(nuveiPlans[nuveiPlData].startAfter.month > 0) {
                    jQuery('#startAfterUnit').val('month');
                    jQuery('#startAfterPeriod').val(nuveiPlans[nuveiPlData].startAfter.month);
                }
                else {
                    jQuery('#startAfterUnit').val('day');
                    jQuery('#startAfterPeriod').val(nuveiPlans[nuveiPlData].startAfter.day);
                }

                break;
            }
        }
    }
</script>