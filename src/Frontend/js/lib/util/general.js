var Taco = Taco || {};
Taco.Util = Taco.Util || {};
Taco.Util.General = Taco.Util.General || {};

// taken from phpjs.org
Taco.Util.General.empty = function(mixed_var) {
  var undef, key, i, len;
  var emptyValues = [undef, null, false, 0, '', '0'];

  for (i = 0, len = emptyValues.length; i < len; i++) {
    if (mixed_var === emptyValues[i]) {
      return true;
    }
  }
  if (typeof mixed_var === 'object') {
    for (key in mixed_var) {
      // TODO: should we check for own properties only?
      //if (mixed_var.hasOwnProperty(key)) {
      return false;
      //}
    }
    return true;
  }
  return false;
};

Taco.Util.General.getParamNames = function(func) {
  var strip_comments = /((\/\/.*$)|(\/\*[\s\S]*?\*\/))/mg;
  var argument_names = /([^\s,]+)/g;
  var fnStr = func.toString().replace(strip_comments, '');
  var result = fnStr.slice(fnStr.indexOf('(')+1, fnStr.indexOf(')')).match(argument_names);
  if(result === null)
     result = [];
  return result;
};

Taco.Util.General.eliminateDuplicates = function(arr) {
  var i, len = arr.length,
  out = [],
  obj = {};

  for(i = 0; i < len; i++) {
    obj[arr[i]] = 0;
  }
  for(i in obj) {
    out.push(i);
  }
  return out;
};

Taco.Util.General.uniqid = function(str, prefix) {
  str = str.replace(/[^0-9a-z]/ig, '');
  var len = str.length;
  var chars = [];
  var id_prefix = '';
  if(typeof prefix != 'undefined') {
    id_prefix = prefix;
  }

  for (var i = 0; i < len; i++) {
    chars[i] = str[Math.floor((Math.random() * len))];
  }
  var filtered = this.eliminateDuplicates(chars);
  return id_prefix + '-' + filtered.join('');
};
