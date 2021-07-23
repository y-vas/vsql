#include <iostream>
#include <fstream>
#include <string>
#include <regex>

using namespace std;

string example(){
   string content = "";

   return " SELECT * FROM Table T "
          "  WHERE TRUE "
          "{ AND T.name = :name } "
   ;
}

int main() {

   string data = example();
   // current key word found          //

   string cuw = "";
   // add char to cuw                 //

   int append = 0;
   int pos[];

   for (int i = 0; i < data.length(); ++i) {

      switch ( data[i] ) {
        case '{':
        case '}':
        break;

      }

   }

   return 0;
}
