trait Foo { fn f(&self); }
trait Bar { fn f(&self); }

struct Baz;

impl Foo for Baz {
    fn f(&self) {
        println!("Foo for Baz");
    }
}

impl Bar for Baz {
    fn f(&self) {
        println!("Bar for Baz");
    }
}

fn main() {
    let b = Baz;
    Foo::f(&b);
    Bar::f(&b);
    <Baz as Foo>::f(&b);
    <Baz as Bar>::f(&b);
}
