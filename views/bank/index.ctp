<div id ="cart">
	<h2>Let Op! sluit dit vensten tijdens<br/>EN NA uw betaling niet af!</h2>

<p>Selecteer alstublieft een bank.</p>

<form name="idealBankSelect" action="<?php echo HOME?>/ideal/sendTransactionRequest" method="post">
	<select name="data[issuerId]">
		<?php echo $issuerList; ?>
	</select>
	<br/><br/>

	<input type="submit" name="Selecteerd" value="Selecteer">
</form>
<br/><br/>
<h2>Als uw bestelling is voltooid ontvangt u een<br/>bevestigingsmail. Dit kan tot tien minuten<br/> duren.</h2>
<br/><br/>
</div>