trait Foo { fn m(&self); }

impl Foo for i32 {
    fn m(&self) {
        println!("i32 {}", *self);
    }
}

impl Foo for String {
    fn m(&self) {
        println!("String {}",  *self);
    }
}

fn do_something<T: Foo>(a: T) {
    a.m();
}

fn ds(a: &Foo){
    a.m();
}


fn main() {
    let x = 4;
    let y = "test".to_string();
    do_something(x);
    ds(&y);
}
