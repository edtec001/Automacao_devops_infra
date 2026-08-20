void reset_default()
{
 
    delay(250);

    digitalWrite(11, LOW);
    digitalWrite(12, LOW);
    digitalWrite(13, LOW);
    digitalWrite(3, LOW);


    
    lcd.clear();
    key = 0;
    submenu=0;
    opc_menu=0;
    roda=0;
    start=0; 
    mark=0; 
    prompt=13;  
}
